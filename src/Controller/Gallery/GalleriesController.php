<?php
declare(strict_types=1);

namespace App\Controller\Gallery;

use App\Controller\BaseController;
use App\Controller\Helpers\Breadcrumbs;
use App\DsShared\Utilities\Paginator\PaginatorPresenter;
use BBC\ProgrammesPagesService\Domain\Entity\Programme;
use BBC\ProgrammesPagesService\Service\ProgrammesAggregationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Podcast full page. Future implementation.
 *
 * When a user click in podcast panel, it takes you to this full page.
 */
class GalleriesController extends BaseController
{
    public function __invoke(
        Programme $programme,
        ProgrammesAggregationService $programmesAggregationService,
        Breadcrumbs $breadcrumbs
    ) {
        if (!in_array($programme->getType(), ['brand', 'series', 'episode'])) {
            throw new NotFoundHttpException();
        }

        $this->overridenDescription = 'Galleries from ' . $programme->getTitle();
        $this->setContextAndPreloadBranding($programme);
        $this->setAtiContentLabels('list-photo-gallery', 'list-galleries');
        $this->setAtiContentId((string) $programme->getPid(), 'pips');

        $page = $this->getPage();
        $limit = 24;

        $gallery = $programmesAggregationService->findDescendantGalleries($programme, $limit, $page);

        $paginator = null;
        $galleriesCount = $programme->getAggregatedGalleriesCount();
        if ($galleriesCount > $limit) {
            $paginator = new PaginatorPresenter($page, $limit, $galleriesCount);
        }

        $this->breadcrumbs = $breadcrumbs
            ->forNetwork($programme->getNetwork())
            ->forEntityAncestry($programme)
            ->forRoute('Galleries', 'programme_galleries', ['pid' => $programme->getPid()])
            ->toArray();

        return $this->renderWithChrome('gallery/galleries.html.twig', [
            'programme' => $programme,
            'galleries' => $gallery,
            'paginatorPresenter' => $paginator,
        ]);
    }
}
