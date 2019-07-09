<?php
declare(strict_types = 1);
namespace App\Controller\FindByPid;

use App\Controller\BaseController;
use BBC\ProgrammesPagesService\Domain\Entity\Collection;

class CollectionController extends BaseController
{
    public function __invoke(Collection $collection)
    {
        $this->setAtiContentLabels('funny', 'reference');
        $this->setContextAndPreloadBranding($collection);

        return $this->renderWithChrome('find_by_pid/collection.html.twig');
    }
}
