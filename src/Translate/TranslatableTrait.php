<?php
declare(strict_types = 1);
namespace App\Translate;

use BBC\ProgrammesPagesService\Domain\ApplicationTime;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;

trait TranslatableTrait
{
    protected $translator;

    /** @var array */
    private $dateFormatterCache = [];

    /**
     * Formatter for international dates in a "Text" format. Need something with translated words in it?
     * You want this one
     *
     * @param DateTimeInterface $dateTime
     * @param string $format    Format must be in ICU format
     *
     * @see http://userguide.icu-project.org/formatparse/datetime
     *
     * @return bool|string
     */
    protected function localDateIntl(DateTimeInterface $dateTime, string $format, DateTimeZone $timeZone = null)
    {
        if (!$timeZone) {
            $timeZone = ApplicationTime::getLocalTimeZone();
        }
        $locale = $this->translator->getLocale();

        // Creating new instances of IntlDateFormatter is expensive.
        // Changing the timezone of an existing instance is less expensive but
        // still has some overhead.
        // Changing the pattern of an existing instance is pretty much free
        // compared to creating a new instance / toggling the timezone.
        // When we render a page we should only ever have one language, and one
        // timezone so create a cached instance of an IntlDateFormatter that is
        // configured with a give language and timezone.
        $cacheKey = $locale . ':' . $timeZone->getName();
        if (!isset($this->dateFormatterCache[$cacheKey])) {
            $this->dateFormatterCache[$cacheKey] = IntlDateFormatter::create(
                $locale,
                IntlDateFormatter::LONG,
                IntlDateFormatter::NONE,
                $timeZone,
                IntlDateFormatter::GREGORIAN
            );
        }

        $this->dateFormatterCache[$cacheKey]->setPattern($format);
        $output = $this->dateFormatterCache[$cacheKey]->format($dateTime->getTimestamp());
        //@TODO figure out if we need RMP\Translate's DateCorrection or if our OS now correctly handles these spellings
        /*
        $dateCorrection = new DateCorrection();
        $output = $dateCorrection->fixSpelling($output, $this->translateProvider->getTranslate()->getLocale());
        */
        return $output;
    }

    protected function tr(
        string $key,
        $substitutions = [],
        $numPlurals = null,
        ?string $domain = null
    ): string {
        if (is_int($substitutions) && is_null($numPlurals)) {
            $numPlurals = $substitutions;
            $substitutions = array('%count%' => $numPlurals);
        }

        if (is_int($numPlurals) && !isset($substitutions['%count%'])) {
            $substitutions['%count%'] = $numPlurals;
        }

        if (isset($substitutions['%count%'])) {
            $key .= ' %count%';
        }

        return $this->translator->trans($key, $substitutions, $domain);
    }
}
