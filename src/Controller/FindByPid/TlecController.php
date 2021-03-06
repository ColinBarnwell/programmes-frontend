<?php
declare(strict_types = 1);

namespace App\Controller\FindByPid;

use App\Controller\Helpers\Breadcrumbs;
use App\Controller\Helpers\StructuredDataHelper;
use App\DsAmen\Factory\PresenterFactory;
use App\DsShared\Factory\HelperFactory;
use App\ExternalApi\Ada\Service\AdaClassService;
use App\ExternalApi\Electron\Service\ElectronService;
use App\ExternalApi\LxPromo\Service\LxPromoService;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Service\CollapsedBroadcastsService;
use BBC\ProgrammesPagesService\Service\ImagesService;
use BBC\ProgrammesPagesService\Service\ProgrammesAggregationService;
use BBC\ProgrammesPagesService\Service\ProgrammesService;
use BBC\ProgrammesPagesService\Service\PromotionsService;
use BBC\ProgrammesPagesService\Service\RelatedLinksService;
use Symfony\Component\HttpFoundation\Request;

/**
 * Top-level Programme Container Page
 *
 * For Top level ProgrammeContainers such as the Doctor Who brand page.
 *
 * We tend to call this "the brand page", but both Brands and Series are both
 * ProgrammeContainers that may appear at the top of the programme hierarchy.
 */
class TlecController extends BaseProgrammeContainerController
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
        // We need to vary on X-Ip_is_uk_combined to avoid returning same cached content to non UK users when there is
        // a vote set to UK only. There is already a vary header set on nginx, this doesn't override the existing "vary"
        // values, this adds a new value to the "vary" list of values
        if ($programme->getOption('telescope_block') !== null
            && isset($programme->getOption('telescope_block')['content']['is_uk_only'])
            && $programme->getOption('telescope_block')['content']['is_uk_only'] === true
        ) {
            $this->response()->headers->set('vary', 'X-Ip_is_uk_combined');
        }
        $this->setAtiContentLabels('brand', 'brand');
        return parent::__invoke(
            $presenterFactory,
            $request,
            $programme,
            $programmesService,
            $promotionsService,
            $collapsedBroadcastsService,
            $aggregationService,
            $imagesService,
            $electronService,
            $adaClassService,
            $helperFactory,
            $relatedLinksService,
            $lxPromoService,
            $structuredDataHelper,
            $breadcrumbs
        );
    }

    protected function hasPriorityPromotion(
        ProgrammeContainer $programme,
        array $promotions,
        bool $shouldDisplayMiniMap
    ): bool {
        if ($programme->getOption('brand_layout') === 'promo' && !empty($promotions) && !$shouldDisplayMiniMap) {
            return true;
        }
        return false;
    }

    protected function shouldDisplayLxPromo(ProgrammeContainer $programme): bool
    {
        return boolval($programme->getOption('livepromo_block'));
    }

    protected function shouldDisplayMiniMap(
        Request $request,
        ProgrammeContainer $programme,
        bool $isVotePriority,
        bool $hasLxPromo
    ): bool {
        if ($request->query->has('__2016minimap')) {
            return (bool) $request->query->get('__2016minimap');
        }

        if ($isVotePriority || $hasLxPromo) {
            return true;
        }

        return filter_var($programme->getOption('brand_2016_layout_use_minimap'), FILTER_VALIDATE_BOOLEAN);
    }

    protected function shouldDisplayPriorityText(): bool
    {
        return true;
    }

    protected function shouldDisplayVote(): bool
    {
        return true;
    }
}
