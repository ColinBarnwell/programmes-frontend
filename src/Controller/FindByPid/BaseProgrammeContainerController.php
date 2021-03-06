<?php
declare(strict_types = 1);

namespace App\Controller\FindByPid;

use App\Controller\BaseController;
use App\Controller\Helpers\Breadcrumbs;
use App\Controller\Helpers\StructuredDataHelper;
use App\DsAmen\Factory\PresenterFactory;
use App\DsShared\Factory\HelperFactory;
use App\ExternalApi\Ada\Service\AdaClassService;
use App\ExternalApi\Electron\Service\ElectronService;
use App\ExternalApi\LxPromo\Service\LxPromoService;
use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use BBC\ProgrammesPagesService\Domain\Entity\Clip;
use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Domain\Entity\Promotion;
use BBC\ProgrammesPagesService\Domain\ValueObject\Pid;
use BBC\ProgrammesPagesService\Domain\ValueObject\Synopses;
use BBC\ProgrammesPagesService\Service\CollapsedBroadcastsService;
use BBC\ProgrammesPagesService\Service\ImagesService;
use BBC\ProgrammesPagesService\Service\ProgrammesAggregationService;
use BBC\ProgrammesPagesService\Service\ProgrammesService;
use BBC\ProgrammesPagesService\Service\PromotionsService;
use BBC\ProgrammesPagesService\Service\RelatedLinksService;
use GuzzleHttp\Promise\FulfilledPromise;
use Symfony\Component\HttpFoundation\Request;

abstract class BaseProgrammeContainerController extends BaseController
{
    public function __invoke(
        PresenterFactory $presenterFactory,
        Request $request,
        ProgrammeContainer $programme,
        ProgrammesService $programmesService,
        PromotionsService $promotionsService,
        CollapsedBroadcastsService $collapsedBroadcastsService,
        ProgrammesAggregationService $aggregationService,
        ImagesService $imagesService,
        ElectronService $electronService,
        AdaClassService $adaClassService,
        HelperFactory $helperFactory,
        RelatedLinksService $relatedLinksService,
        LxPromoService $lxPromoService,
        StructuredDataHelper $structuredDataHelper,
        Breadcrumbs $breadcrumbs
    ) {
        if ($programme->getNetwork() && $programme->getNetwork()->isInternational()) {
            // "International" services are UTC, all others are Europe/London (the default)
            ApplicationTime::setLocalTimeZone('UTC');
        }
        $this->setContextAndPreloadBranding($programme);
        $this->setInternationalStatusAndTimezoneFromContext($programme);
        $this->setAtiContentId((string) $programme->getPid(), 'pips');

        // TODO check $programme->getPromotionsCount() once it is populated in
        // Faucet to potentially save on a DB query
        $promotions = $promotionsService->findAllActivePromotionsByContext($programme);

        $clips = [];
        if ($programme->getAvailableClipsCount() > 0 && $programme->getOption('show_clip_cards')) {
            $clips = $aggregationService->findStreamableDescendantClips($programme, 4);
        }

        $relatedLinks = [];
        if ($programme->getRelatedLinksCount() > 0) {
            $relatedLinks = $relatedLinksService->findByRelatedToProgramme($programme, ['related_site', 'miscellaneous']);
        }

        $upcomingBroadcast = null;
        $onDemandEpisode = null;
        $upcomingRepeatsAndDebutsCounts = ['debuts' => 0, 'repeats' => 0];
        if ($programme->getAggregatedEpisodesCount() > 0) {
            $onDemandEpisodes = $aggregationService->findStreamableOnDemandEpisodes($programme, 1);
            $onDemandEpisode = $onDemandEpisodes[0] ?? null;
            $upcomingBroadcast = $collapsedBroadcastsService
                ->findNextDebutOrRepeatOnByProgrammeWithFullServicesOfNetworksList($programme);
            $upcomingRepeatsAndDebutsCounts = $collapsedBroadcastsService->countUpcomingRepeatsAndDebutsByProgramme($programme);
        }

        $galleries = [];
        if ($programme->getAggregatedGalleriesCount() > 0 && $programme->getOption('show_gallery_cards')) {
            $galleries = $aggregationService->findDescendantGalleries($programme, 4);
        }

        $lastOn = $this->getLastOn($programme, $collapsedBroadcastsService);
        $comingSoonPromo = $this->getComingSoonPromotion($imagesService, $programme);

        /* Start Promises */
        $lxPromoPromise = new FulfilledPromise(null);
        if ($this->shouldDisplayLxPromo($programme)) {
            $lxPromoPromise = $lxPromoService->fetchByProgrammeContainer($programme);
        }

        $relatedTopicsPromise = new FulfilledPromise([]);
        if ($this->shouldDisplayTopics($programme)) {
            // Less than 50 episodes (through ancestry)...
            $usePerContainerValues = $programme->getAggregatedEpisodesCount() >= 50;
            $relatedTopicsPromise = $adaClassService->findRelatedClassesByContainer($programme, $usePerContainerValues, 5);
        }

        $promises = [
            'relatedTopics' => $relatedTopicsPromise,
            'supportingContentItems' => $electronService->fetchSupportingContentItemsForProgramme($programme),
            'lxPromo' => $lxPromoPromise,
        ];

        $resolvedPromises = $this->resolvePromises($promises);
        /* End promises */

        // Map parameters
        $isVotePriority = $this->isVotePriority($programme);
        $shouldDisplayMiniMap = $this->shouldDisplayMiniMap($request, $programme, $isVotePriority, isset($resolvedPromises['lxPromo']));

        // Promo priority
        $priorityPromotion = null;
        if ($this->hasPriorityPromotion($programme, $promotions, $shouldDisplayMiniMap)) {
            $priorityPromotion = array_shift($promotions);
        }

        $mapPresenter = $presenterFactory->mapPresenter(
            $programme,
            $upcomingBroadcast,
            $lastOn,
            $priorityPromotion,
            $comingSoonPromo,
            $onDemandEpisode,
            $upcomingRepeatsAndDebutsCounts['debuts'],
            $upcomingRepeatsAndDebutsCounts['repeats'],
            $shouldDisplayMiniMap
        );


        $schema = $this->getSchema($structuredDataHelper, $programme, $onDemandEpisode, $upcomingBroadcast, $clips);

        $parameters = [
            'programme' => $programme,
            'promotions' => $promotions,
            'clips' => $clips,
            'galleries' => $galleries,
            'mapPresenter' => $mapPresenter,
            'shouldDisplayVote' => $this->shouldDisplayVote(),
            'isVotePriority' => $isVotePriority,
            'canDisplayVoteInRegion' => $this->canDisplayVoteInRegion($programme->getOption('telescope_block'), $this->request()),
            'relatedLinks' => $relatedLinks,
            'schema' => $schema,
            'shouldDisplayPriorityText' => $this->shouldDisplayPriorityText(),
        ];

        $parameters = array_merge($parameters, $resolvedPromises);

        $this->breadcrumbs = $breadcrumbs
            ->forNetwork($programme->getNetwork())
            ->forEntityAncestry($programme)
            ->toArray();

        return $this->renderWithChrome('find_by_pid/programme_container.html.twig', $parameters);
    }

    abstract protected function hasPriorityPromotion(
        ProgrammeContainer $programme,
        array $promotions,
        bool $shouldDisplayMiniMap
    ): bool;

    abstract protected function shouldDisplayLxPromo(ProgrammeContainer $programme): bool;

    abstract protected function shouldDisplayMiniMap(
        Request $request,
        ProgrammeContainer $programme,
        bool $isVotePriority,
        bool $hasLxPromo
    ): bool;

    abstract protected function shouldDisplayPriorityText(): bool;

    abstract protected function shouldDisplayVote(): bool;

    private function shouldDisplayTopics(ProgrammeContainer $programme): bool
    {
        return boolval($programme->getOption('show_enhanced_navigation'));
    }

    private function getComingSoonPromotion(ImagesService $imagesService, ProgrammeContainer $programme): ?Promotion
    {
        $comingSoonBlock = $programme->getOption('comingsoon_block');
        if (empty($comingSoonBlock['content']['promotions'])) {
            return null;
        }

        $comingSoon = $comingSoonBlock['content']['promotions'];
        if (!array_key_exists('promotion_title', $comingSoon)) {
            $comingSoon = reset($comingSoon);
        }

        $pid = new Pid($comingSoon['promoted_item_id']);
        $image = $imagesService->findByPid($pid);
        if (is_null($image)) {
            return null; // This should never happen
        }

        $synopses = new Synopses($comingSoon['short_synopsis']);

        return new Promotion(
            $pid,
            $image,
            $comingSoon['promotion_title'],
            $synopses,
            $comingSoon['url'],
            0,
            filter_var($comingSoon['super_promo'], FILTER_VALIDATE_BOOLEAN),
            []
        );
    }

    private function getLastOn(
        ProgrammeContainer $programme,
        CollapsedBroadcastsService $collapsedBroadcastsService
    ): ?CollapsedBroadcast {
        // World News brand pages are the only ones that show the Last On column in the MAP. The Last On column
        // shows a collapsed broadcast, which has a list of networks and services of the broadcasts, which means
        // it needs the full list of services for the networks. If something else starts using the Last On
        // column, this has to be updated, as does the MAP, or else stuff will blow up.
        if ($programme->getNetwork() && $programme->getNetwork()->isWorldNews()) {
            $lastOn = $collapsedBroadcastsService->findPastByProgrammeWithFullServicesOfNetworksList($programme, 1);
        } else {
            $lastOn = $collapsedBroadcastsService->findPastByProgramme($programme, 1);
        }

        return $lastOn[0] ?? null;
    }

    private function isVotePriority(ProgrammeContainer $programme): bool
    {
        return $programme->getOption('brand_layout') === 'vote' && $programme->getOption('telescope_block') !== null;
    }

    /**
     * @param StructuredDataHelper $structuredDataHelper
     * @param ProgrammeContainer $programme
     * @param Episode|null $onDemandEpisode
     * @param CollapsedBroadcast|null $upcomingBroadcast
     * @param Clip[] $clips
     * @return array
     * @throws \BBC\ProgrammesPagesService\Domain\Exception\DataNotFetchedException
     */
    private function getSchema(
        StructuredDataHelper $structuredDataHelper,
        ProgrammeContainer $programme,
        ?Episode $onDemandEpisode,
        ?CollapsedBroadcast $upcomingBroadcast,
        array $clips = []
    ): array {
        $schemaContext = $structuredDataHelper->getSchemaForProgrammeContainerAndParents($programme);

        // clips
        foreach ($clips as $clip) {
            $schemaContext['hasPart'][] = $structuredDataHelper->getSchemaForClip($clip, false);
        }

        // publications
        if (!$onDemandEpisode && !$upcomingBroadcast) {
            return $structuredDataHelper->prepare($schemaContext);
        }

        if ($onDemandEpisode && $upcomingBroadcast) {
            if ($this->isSamePublication($onDemandEpisode, $upcomingBroadcast)) {
                $episode = $structuredDataHelper->getSchemaForEpisode($onDemandEpisode, true);
                $episode['publication'] = [
                    $structuredDataHelper->getSchemaForOnDemand($onDemandEpisode),
                    $structuredDataHelper->getSchemaForCollapsedBroadcast($upcomingBroadcast),
                ];
                $schemaContext['episode'] = $episode;

                return $structuredDataHelper->prepare($schemaContext);
            }

            $od = $structuredDataHelper->getSchemaForEpisode($onDemandEpisode, true);
            $od['publication'] = $structuredDataHelper->getSchemaForOnDemand($onDemandEpisode);
            $cb = $structuredDataHelper->getSchemaForEpisode($upcomingBroadcast->getProgrammeItem(), true);
            $cb['publication'] = $structuredDataHelper->getSchemaForCollapsedBroadcast($upcomingBroadcast);
            $schemaContext['episode'] = [$od, $cb];

            return $structuredDataHelper->prepare($schemaContext);
        }

        $episode = $onDemandEpisode ?? $upcomingBroadcast->getProgrammeItem();
        $episodeSchema = $structuredDataHelper->getSchemaForEpisode($episode, true);

        if ($onDemandEpisode) {
            $episodeSchema['publication'] = $structuredDataHelper->getSchemaForOnDemand($onDemandEpisode);
        } else {
            $episodeSchema['publication'] = $structuredDataHelper->getSchemaForCollapsedBroadcast($upcomingBroadcast);
        }

        return $structuredDataHelper->prepare($schemaContext);
    }

    private function isSamePublication(Episode $onDemandEpisode, CollapsedBroadcast $upcomingBroadcast)
    {
        return (string) $onDemandEpisode->getPid() == (string) $upcomingBroadcast->getProgrammeItem()->getPid();
    }

    /**
     * Return true unless the vote is set to "only available in the UK" and the connections is not in the UK
     *
     * @param array|null $telescopeBlock
     * @param Request $request
     * @return bool
     */
    private function canDisplayVoteInRegion(?array $telescopeBlock, Request $request): bool
    {
        if (isset($telescopeBlock['content']['is_uk_only'])
            && $telescopeBlock['content']['is_uk_only'] === true
            && $request->headers->get('X-Ip_is_uk_combined') === 'no'
        ) {
            return false;
        }
        return true;
    }
}
