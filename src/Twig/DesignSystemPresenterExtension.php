<?php
declare(strict_types = 1);

namespace App\Twig;

use App\Ds2013\Presenter as Ds2013Presenter;
use App\Ds2013\Factory\PresenterFactory as Ds2013PresenterFactory;
use App\DsAmen\Presenter as DsAmenPresenter;
use App\DsAmen\Factory\PresenterFactory as DsAmenPresenterFactory;
use App\DsShared\Presenter as DsSharedPresenter;
use App\DsShared\Factory\PresenterFactory as DsSharedPresenterFactory;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class DesignSystemPresenterExtension extends AbstractExtension
{
    /** @var Ds2013PresenterFactory */
    private $ds2013PresenterFactory;

    /** @var DsAmenPresenterFactory */
    private $dsAmenPresenterFactory;

    /** @var DsSharedPresenterFactory */
    private $dsSharedPresenterFactory;

    public function __construct(
        Ds2013PresenterFactory $ds2013PresenterFactory,
        DsAmenPresenterFactory $dsAmenPresenterFactory,
        DsSharedPresenterFactory $dsSharedPresenterFactory
    ) {
        $this->ds2013PresenterFactory = $ds2013PresenterFactory;
        $this->dsAmenPresenterFactory = $dsAmenPresenterFactory;
        $this->dsSharedPresenterFactory = $dsSharedPresenterFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('ds2013', [$this, 'ds2013'], [
                'is_safe' => ['html'],
                'is_variadic' => true,
                'needs_environment' => true,
            ]),
            new TwigFunction('ds2013_presenter', [$this, 'ds2013Presenter'], [
                'is_safe' => ['html'],
                'needs_environment' => true,
            ]),
            new TwigFunction('ds_amen', [$this, 'dsAmen'], [
                'is_safe' => ['html'],
                'is_variadic' => true,
                'needs_environment' => true,
            ]),
            new TwigFunction('ds_amen_presenter', [$this, 'dsAmenPresenter'], [
                'is_safe' => ['html'],
                'needs_environment' => true,
            ]),
            new TwigFunction('ds_shared', [$this, 'dsShared'], [
                'is_safe' => ['html'],
                'is_variadic' => true,
                'needs_environment' => true,
            ]),
            new TwigFunction('ds_shared_presenter', [$this, 'dsSharedPresenter'], [
                'is_safe' => ['html'],
                'needs_environment' => true,
            ]),
        ];
    }

    public function ds2013(
        Environment $twigEnv,
        string $presenterName,
        array $presenterArguments = []
    ): string {
        $presenter = $this->ds2013PresenterFactory->{$presenterName . 'Presenter'}(...$presenterArguments);

        return $this->ds2013Presenter($twigEnv, $presenter);
    }

    public function ds2013Presenter(
        Environment $twigEnv,
        Ds2013Presenter $presenter
    ): string {
        return $twigEnv->render(
            $presenter->getTemplatePath(),
            [$presenter->getTemplateVariableName() => $presenter]
        );
    }

    public function dsAmen(
        Environment $twigEnv,
        string $presenterName,
        array $presenterArguments = []
    ): string {
        $presenter = $this->dsAmenPresenterFactory->{$presenterName . 'Presenter'}(...$presenterArguments);
        return $this->dsAmenPresenter($twigEnv, $presenter);
    }

    public function dsAmenPresenter(
        Environment $twigEnv,
        DsAmenPresenter $presenter
    ) {
        return $twigEnv->render(
            $presenter->getTemplatePath(),
            [$presenter->getTemplateVariableName() => $presenter]
        );
    }

    public function dsShared(
        Environment $twigEnv,
        string $presenterName,
        array $presenterArguments = []
    ): string {
        $presenter = $this->dsSharedPresenterFactory->{$presenterName . 'Presenter'}(...$presenterArguments);
        return $this->dsSharedPresenter($twigEnv, $presenter);
    }

    public function dsSharedPresenter(
        Environment $twigEnv,
        DsSharedPresenter $presenter
    ) {
        return $twigEnv->render(
            $presenter->getTemplatePath(),
            [$presenter->getTemplateVariableName() => $presenter]
        );
    }
}
