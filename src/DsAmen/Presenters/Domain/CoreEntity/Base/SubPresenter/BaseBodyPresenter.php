<?php
declare(strict_types = 1);

namespace App\DsAmen\Presenters\Domain\CoreEntity\Base\SubPresenter;

use App\DsAmen\Presenter;
use BBC\ProgrammesPagesService\Domain\Entity\CoreEntity;
use BBC\ProgrammesPagesService\Domain\Entity\MasterBrand;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use BBC\ProgrammesPagesService\Domain\ValueObject\PartialDate;

abstract class BaseBodyPresenter extends Presenter
{
    /** @var CoreEntity */
    protected $coreEntity;

    /** @var PartialDate|null  */
    protected $releaseDate = null;

    /** @var array */
    protected $options = [
        'show_synopsis' => false,
        'synopsis_class' => 'invisible visible@gel3',
        'show_release_date' => false,
        'show_masterbrand' => false,
        'full_details_class' => 'programme__details media__meta-group',
    ];

    public function __construct(CoreEntity $coreEntity, array $options = [])
    {
        parent::__construct($options);
        $this->coreEntity = $coreEntity;

        if ($this->coreEntity instanceof ProgrammeItem && $this->coreEntity->getReleaseDate()) {
            $this->releaseDate = $this->coreEntity->getReleaseDate();
        }
    }

    public function getReleaseDate(): ?PartialDate
    {
        return $this->releaseDate;
    }

    public function getSynopsis(): string
    {
        return $this->coreEntity->getShortSynopsis();
    }

    public function hasFullDetails(): bool
    {
        return $this->getOption('show_synopsis') || $this->hasReleaseDate();
    }

    public function hasReleaseDate(): bool
    {
        return $this->getOption('show_release_date') && !is_null($this->releaseDate);
    }

    public function getDisplayMasterbrand(): ?MasterBrand
    {
        if ($this->getOption('show_masterbrand')) {
            $programmeMasterbrand = $this->coreEntity->getMasterBrand();
            $contextMasterbrand = $this->getOption('context_programme') ? $this->getOption('context_programme')->getMasterbrand() : null;
            if ($programmeMasterbrand != $contextMasterbrand) {
                return $programmeMasterbrand;
            }
        }
        return null;
    }
}
