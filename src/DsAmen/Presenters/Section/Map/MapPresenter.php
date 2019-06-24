<?php
declare(strict_types = 1);

namespace App\DsAmen\Presenters\Section\Map;

use App\DsAmen\Presenter;
use App\DsAmen\Presenters\Section\Map\SubPresenter\ComingSoonPresenter;
use App\DsAmen\Presenters\Section\Map\SubPresenter\LastOnPresenter;
use App\DsAmen\Presenters\Section\Map\SubPresenter\OnDemandPresenter;
use App\DsAmen\Presenters\Section\Map\SubPresenter\ProgrammeInfoPresenter;
use App\DsAmen\Presenters\Section\Map\SubPresenter\PromoPriorityPresenter;
use App\DsAmen\Presenters\Section\Map\SubPresenter\SocialBarPresenter;
use App\DsAmen\Presenters\Section\Map\SubPresenter\TxPresenter;
use App\DsShared\Factory\HelperFactory;
use App\Translate\TranslateProvider;
use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Domain\Entity\Promotion;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Media Availability Panel Presenter
 * Default layout is 3 columns and no minimapping. Left column takes up 1/2 the page, the others the other half
 * See docs/MAP.md for more info
 */
class MapPresenter extends Presenter
{
    /** @var Promotion|null */
    protected $comingSoonPromo;

    /** @var int */
    protected $debutsCount;

    /** @var Promotion|null */
    protected $priorityPromotion;

    /** @var \App\DsShared\Factory\HelperFactory */
    protected $helperFactory;

    /** @var CollapsedBroadcast|null */
    protected $lastOn;

    /** @var Presenter */
    protected $leftColumn;

    /** @var string */
    protected $leftGridClasses = '1/2@gel3b';

    /** @var ProgrammeContainer */
    protected $programme;

    /** @var int */
    protected $repeatsCount;

    /** @var Presenter[] */
    protected $rightColumns = [];

    /** @var string */
    protected $rightGridClasses = '1/2@gel3b';

    /** @var UrlGeneratorInterface */
    protected $router;

    /** @var bool */
    protected $showMiniMap;

    /** @var Episode|null*/
    protected $streamableEpisode;

    /** @var TranslateProvider */
    protected $translateProvider;

    /** @var CollapsedBroadcast|null */
    protected $upcomingBroadcast;

    public function __construct(
        HelperFactory $helperFactory,
        TranslateProvider $translateProvider,
        UrlGeneratorInterface $router,
        ProgrammeContainer $programme,
        ?CollapsedBroadcast $upcomingBroadcast,
        ?CollapsedBroadcast $lastOn,
        ?Promotion $priorityPromotion,
        ?Promotion $comingSoonPromo,
        ?Episode $streamableEpisode,
        int $debutsCount,
        int $repeatsCount,
        bool $showMiniMap,
        array $options = []
    ) {
        // Set class properties
        parent::__construct($options);
        $this->helperFactory = $helperFactory;
        $this->translateProvider = $translateProvider;
        $this->router = $router;
        $this->programme = $programme;
        $this->upcomingBroadcast = $upcomingBroadcast;
        $this->lastOn = $lastOn;
        $this->priorityPromotion = $priorityPromotion;
        $this->comingSoonPromo = $comingSoonPromo;
        $this->streamableEpisode = $streamableEpisode;
        $this->debutsCount = $debutsCount;
        $this->repeatsCount = $repeatsCount;
        $this->showMiniMap = $showMiniMap;

        if (!$this->showMap()) {
            return;
        }

        if ($this->showThirdColumn()) {
            // Construct map columns
            $this->constructThreeColumnMap();
        } else {
            $this->constructTwoColumnMap();
        }
        $this->constructLeftColumn();
    }

    public function getLeftColumn(): Presenter
    {
        return $this->leftColumn;
    }

    public function getLeftGridClasses(): string
    {
        return $this->leftGridClasses;
    }

    public function getProgramme(): Programme
    {
        return $this->programme;
    }

    /**
     * @return Presenter[]
     */
    public function getRightColumns(): array
    {
        return $this->rightColumns;
    }

    public function getRightGridClasses(): string
    {
        return $this->rightGridClasses;
    }

    public function getSocialBarPresenter(): ?SocialBarPresenter
    {
        return new SocialBarPresenter($this->programme);
    }

    public function showMap(): bool
    {
        return $this->programme->getAggregatedEpisodesCount() || $this->hasComingSoon();
    }

    /**
     * Must be run after construct(Two|Three)ColumnMap
     */
    protected function constructLeftColumn(): void
    {
        $leftColumnOptions = ['is_three_column' => $this->countTotalColumns() === 3];
        if ($this->priorityPromotion) {
            $this->leftColumn = new PromoPriorityPresenter(
                $this->programme,
                $this->priorityPromotion,
                $leftColumnOptions
            );
        } else {
            $leftColumnOptions['show_mini_map'] = $this->showMiniMap;
            $this->leftColumn = new ProgrammeInfoPresenter(
                $this->programme,
                $leftColumnOptions
            );
        }
    }

    /**
     * This is the left column and the main right columns
     * The social column is ignored
     *
     * @return int
     */
    protected function countTotalColumns(): int
    {
        return 1 + count($this->rightColumns);
    }

    private function constructThreeColumnMap(): void
    {
        if ($this->showMiniMap) {
            $this->leftGridClasses = '1/3@gel3b';
            $this->rightGridClasses = '2/3@gel3b';
        }

        // Add columns to map

        // The Last On column requires the collapsed broadcast to have a full list of the services of the networks
        // that had this programme broadcast. If something other than World News starts using the Last On column, the
        // controllers that fetch this collapsed broadcast will have to be updated to fetch the full list of services
        // as well. The TlecController is one of them (L#93).
        if ($this->isWorldNews()) {
            $this->rightColumns[] = new LastOnPresenter($this->programme, $this->lastOn, ['show_mini_map' => $this->showMiniMap]);
        } else {
            $hasAnUpcomingEpisode = $this->upcomingBroadcast && $this->upcomingBroadcast->getProgrammeItem()->getStreamableFrom() && !$this->upcomingBroadcast->getProgrammeItem()->hasPlayableDestination();

            $this->rightColumns[] = new OnDemandPresenter(
                $this->translateProvider,
                $this->router,
                $this->programme,
                $this->streamableEpisode,
                $hasAnUpcomingEpisode,
                $this->lastOn,
                ['full_width' => false, 'show_mini_map' => $this->showMiniMap]
            );
        }

        $options = ['show_mini_map' => $this->showMiniMap];

        if ($this->hasComingSoon() && !$this->upcomingBroadcast) {
            $this->rightColumns[] = new ComingSoonPresenter(
                $this->programme,
                $this->comingSoonPromo,
                $options
            );
        } else {
            $this->rightColumns[] = new TxPresenter(
                $this->helperFactory->getLiveBroadcastHelper(),
                $this->translateProvider,
                $this->router,
                $this->programme,
                $this->upcomingBroadcast,
                $this->debutsCount,
                $this->repeatsCount,
                $options
            );
        }
    }

    private function constructTwoColumnMap(): void
    {
        $this->leftGridClasses = '2/3@gel3b';
        $this->rightGridClasses = '1/3@gel3b';

        $this->rightColumns[] = new OnDemandPresenter(
            $this->translateProvider,
            $this->router,
            $this->programme,
            $this->streamableEpisode,
            false,
            null,
            [
                'full_width' => true,
                'show_mini_map' => $this->showMiniMap,
            ]
        );
    }

    private function isWorldNews(): bool
    {
        return $this->programme->getNetwork() && $this->programme->getNetwork()->isWorldNews();
    }

    private function showThirdColumn(): bool
    {
        $hasBroadcastInLast18Months = $this->lastOn ? $this->lastOn->getStartAt()->wasWithinLast('18 months') : false;

        return $this->upcomingBroadcast || $hasBroadcastInLast18Months || $this->hasComingSoon() || $this->isWorldNews();
    }

    private function hasComingSoon(): bool
    {
        return $this->comingSoonPromo || $this->programme->getOption('comingsoon_textonly');
    }
}
