<?php
declare(strict_types = 1);

namespace App\DsAmen\Presenters\Section\Map\SubPresenter;

use App\Exception\InvalidOptionException;
use BBC\ProgrammesPagesService\Domain\Entity\CollapsedBroadcast;
use BBC\ProgrammesPagesService\Domain\Entity\Episode;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeContainer;
use BBC\ProgrammesPagesService\Domain\Entity\ProgrammeItem;
use Exception;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatorInterface;

class OnDemandPresenter extends RightColumnPresenter
{
    /** @var mixed[] */
    protected $options = [
        'full_width' => false, // The full width of the right hand MAP column
        'show_mini_map' => false,
    ];

    /** @var string */
    private $class = '1/2@gel1b';

    /**
     * An upcoming episode is an Episode that will be broadcast and streamable in the future
     * @var bool
     */
    private $hasUpcomingEpisode;

    /**
     * The last on is the CollapsedBroadcast for the last thing that was on.
     * Its associated episode may or may not be streamable yet
     * @var CollapsedBroadcast|null
     */
    private $lastOn;

    /**
     * An streamable episode is an Episode that is streamable right now
     * @var Episode|null
     */
    private $streamableEpisode;

    private $translator;

    /** @var UrlGeneratorInterface */
    private $router;

    public function __construct(
        TranslatorInterface $translator,
        UrlGeneratorInterface $router,
        ProgrammeContainer $programmeContainer,
        ?Episode $streamableEpisode,
        bool $hasUpcomingEpisode,
        ?CollapsedBroadcast $lastOn,
        $options = []
    ) {
        parent::__construct($programmeContainer, $options);
        $this->lastOn = $lastOn;
        $this->streamableEpisode = $streamableEpisode;
        $this->hasUpcomingEpisode = $hasUpcomingEpisode;
        $this->translator = $translator;
        $this->router = $router;
        if ($this->getOption('full_width')) {
            $this->class = '1/1';
            $this->defaultImageSize = 320;
            $this->imageSizes = [768 => 1/3, 1008 => '324px', 1280 => '414px'];
        }
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getPendingEpisode(): ?ProgrammeItem
    {
        if ($this->lastOn === null) {
            return null;
        }

        // The episode has been and gone if streamable_from is null
        if (!$this->lastOn->getProgrammeItem() || $this->lastOn->getProgrammeItem()->getStreamableFrom() === null) {
            return null;
        }

        return $this->lastOn->getProgrammeItem();
    }

    public function getBadgeTranslationString(): string
    {
        if ($this->streamableEpisode === null && $this->lastOn === null) {
            throw new Exception('Streamable or LastOn must be set in order to call ' . __FUNCTION__);
        }
        if ($this->episodeIsPending()) {
            return 'coming_soon';
        }
        // Coming soon can be displayed on Radio and TV pages.
        // New and New Series should only display on TV pages.
        if ($this->treatAsRadio()) {
            return '';
        }
        // If the parent is the TLEO (e.g. Eastenders) we don't want to show a new badge for each episode
        // Otherwise (e.g. Mongrels) the parent will be a series, which we do want badges for each episode.
        if ($this->streamableEpisode === null || !$this->streamableEpisode->getParent() || $this->streamableEpisode->getParent()->isTleo()) {
            return '';
        }

        // If this episode was never broadcast, or the broadcast happened over 7 days ago, don't show the badge.
        // Sometimes an episode first broadcast date could be in the future, but the episode is already available
        // on demand
        $firstBroadcastDate = $this->streamableEpisode->getFirstBroadcastDate();
        if ($firstBroadcastDate === null || ($firstBroadcastDate->isPast() && !$firstBroadcastDate->wasWithinLast('7 days'))) {
            return '';
        }

        return $this->streamableEpisode->getPosition() === 1 ? 'new_series' : 'new';
    }

    public function getStreamableEpisode(): ?Episode
    {
        return $this->streamableEpisode;
    }

    public function getTitleTranslationString(): string
    {
        return $this->treatAsRadio() ? 'available_now' : 'available_on_iplayer_short';
    }

    public function getTranslatedStringForOnDemandNotAvailable(): string
    {
        return $this->treatAsRadio() ? $this->translator->trans('available_count %count%', ['%count%' => 0]) : $this->translator->trans('not_available_iplayer');
    }

    public function hasUpcomingEpisode(): bool
    {
        return $this->hasUpcomingEpisode;
    }

    /**
     * This is when a programme has finished broadcasting, but is not available to stream yet.
     * So instead of showing the old streamable episode, we show the just broadcast episode with a coming soon badge
     *
     * @return bool
     */
    public function episodeIsPending(): bool
    {
        if (!$this->lastOn || !$this->lastOn->getProgrammeItem()) {
            return false;
        }
        $hasFutureAvailablity = !($this->lastOn->getProgrammeItem()->getStreamableFrom() === null || $this->lastOn->getProgrammeItem()->hasPlayableDestination());
        if (!$hasFutureAvailablity) {
            return false;
        }
        // If the broadcast was over 7 days ago, but still isn't streamable, revert back to the previous episode
        return $this->lastOn->getStartAt()->wasWithinLast('7 days');
    }

    public function shouldShowImage(): bool
    {
        return !$this->getOption('show_mini_map');
    }

    public function getEpisodeGuideLink(): string
    {
        if ($this->treatAsRadio() || !$this->programmeContainer->isTlec()) {
            // iPlayer only have a brand, not a series episode guide
            return $this->router->generate('programme_episodes_player', ['pid' => $this->programmeContainer->getPid()]);
        }
        return $this->router->generate('iplayer_episodes', ['pid' => $this->programmeContainer->getPid()]);
    }

    public function getEpisodeGuideTitleTranslation(): string
    {
        $translationKey = $this->treatAsRadio() ? 'all_episodes_iplayer_radio_title' : 'all_episodes_iplayer_title';
        return $this->translator->trans(
            $translationKey,
            ['%1' => $this->programmeContainer->getTitle()]
        );
    }

    public function getOnDemandEpisode(): ?ProgrammeItem
    {
        if ($this->episodeIsPending()) {
            return $this->getPendingEpisode();
        }
        if ($this->getStreamableEpisode()) {
            return $this->getStreamableEpisode();
        }

        return null;
    }

    protected function treatAsRadio()
    {
        $onDemandEpisode = $this->getOnDemandEpisode();
        if ($onDemandEpisode) {
            return $onDemandEpisode->isAudio();
        }
        return $this->programmeContainer->isRadio();
    }

    protected function validateOptions(array $options): void
    {
        parent::validateOptions($options);

        if (!is_bool($options['full_width'])) {
            throw new InvalidOptionException('full_width option must be a boolean');
        }
    }
}
