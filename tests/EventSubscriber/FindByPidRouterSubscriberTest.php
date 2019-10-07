<?php
declare(strict_types = 1);
namespace Tests\App\EventSubscriber;

use App\EventSubscriber\FindByPidRouterSubscriber;
use App\Exception\ProgrammeOptionsRedirectHttpException;
use BBC\ProgrammesPagesService\Domain\Entity\Brand;
use BBC\ProgrammesPagesService\Domain\Entity\Clip;
use BBC\ProgrammesPagesService\Domain\Entity\Collection;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\Franchise;
use BBC\ProgrammesPagesService\Domain\Entity\Gallery;
use BBC\ProgrammesPagesService\Domain\Entity\Options;
use BBC\ProgrammesPagesService\Domain\Entity\Season;
use BBC\ProgrammesPagesService\Domain\Entity\Series;
use BBC\ProgrammesPagesService\Domain\Entity\Version;
use BBC\ProgrammesPagesService\Service\CoreEntitiesService;
use BBC\ProgrammesPagesService\Service\ServiceFactory;
use BBC\ProgrammesPagesService\Service\VersionsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class FindByPidRouterSubscriberTest extends TestCase
{
    /**
     * @dataProvider entityDataProvider
     */
    public function testEntityResponse($coreEntity, $version, $expectedController, $expectedAttrName)
    {
        $request = $this->request();

        $this->buildSubscriber(
            $coreEntity,
            $version
        )->updateController($this->event($request));

        // Controller is resolved correctly
        $this->assertEquals($expectedController, $request->attributes->get('_controller'));
        // Expose the entity on the given attribute name

        if ($coreEntity) {
            $this->assertEquals($coreEntity, $request->attributes->get($expectedAttrName));
        } elseif ($version) {
            $this->assertEquals($version, $request->attributes->get($expectedAttrName));
        }
    }

    public function entityDataProvider()
    {
        $tleo = $this->createMock(Brand::class);
        $series = $this->createMock(Series::class);
        $series->method('getParent')->willReturn($tleo);

        return [
            [$tleo, null, 'App\Controller\FindByPid\TlecController', 'programme'],
            [$series, null, 'App\Controller\FindByPid\SeriesController', 'programme'],
            [$this->createMock(Episode::class), null, 'App\Controller\FindByPid\EpisodeController', 'episode'],
            [$this->createMock(Clip::class), null, 'App\Controller\FindByPid\ClipController', 'clip'],
            [$this->createMock(Collection::class), null, 'App\Controller\FindByPid\CollectionController', 'collection'],
            [$this->createMock(Gallery::class), null, 'App\Controller\FindByPid\GalleryController', 'gallery'],
            [$this->createMock(Season::class), null, 'App\Controller\FindByPid\SeasonController', 'season'],
            [null, $this->createMock(Version::class), 'App\Controller\FindByPid\VersionController', 'version'],
        ];
    }

    public function testThrowsExceptionIfFranchiseFound()
    {
        $request = $this->request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The item with PID "b0000001" was a franchise, which v3 does not support');

        $this->buildSubscriber(
            $this->createMock(Franchise::class)
        )->updateController($this->event($request));
    }

    public function testEntityTriggersRedirectsIfSetInOptions()
    {
        $coreEntity = $this->createMock(Brand::class);
        $coreEntity->method('getOptions')->willReturn(new Options([
            'pid_override_url' => 'http://example.com',
            'pid_override_code' => 301,
        ]));

        $request = $this->request();

        $this->expectException(ProgrammeOptionsRedirectHttpException::class);
        $this->expectExceptionMessage('Programme Options has triggered a "301" redirect to "http://example.com"');

        $this->buildSubscriber($coreEntity)->updateController($this->event($request));
    }

    public function testOnlyRunsOnMasterRequests()
    {
        $request = $this->request();

        $this->buildSubscriber()->updateController($this->event($request, false));

        $this->assertEquals('!find_by_pid', $request->attributes->get('_controller'));
    }

    public function testOnlyRunsOnFindByPidRequests()
    {
        $request = new Request([], [], ['_controller' => 'zzz']);

        $this->buildSubscriber()->updateController($this->event($request, false));

        $this->assertEquals('zzz', $request->attributes->get('_controller'));
    }

    public function testThrowsExceptionIfNoResultsFound()
    {
        $request = $this->request();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The item with PID "b0000001" was not found');

        $this->buildSubscriber()->updateController($this->event($request));
    }

    private function buildSubscriber(
        CoreEntity $coreEntityResult = null,
        Version $versionResult = null
    ) {
        $coreEntitiesService = $this->createMock(CoreEntitiesService::class);
        $coreEntitiesService->method('findByPidFull')->willReturn($coreEntityResult);

        $versionsService = $this->createMock(VersionsService::class);
        $versionsService->method('findByPidFull')->willReturn($versionResult);

        $serviceFactory = $this->createMock(ServiceFactory::class);
        $serviceFactory->method('getCoreEntitiesService')->willReturn($coreEntitiesService);
        $serviceFactory->method('getVersionsService')->willReturn($versionsService);

        return new FindByPidRouterSubscriber($serviceFactory);
    }

    private function request()
    {
        $attributes = ['pid' => 'b0000001', '_controller' => '!find_by_pid'];

        return new Request([], [], $attributes);
    }

    private function event(Request $request, bool $isMasterRequest = true)
    {
        return new GetResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $isMasterRequest ? HttpKernelInterface::MASTER_REQUEST : HttpKernelInterface::SUB_REQUEST
        );
    }
}
