<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\Form\Type;

use Sonata\Form\Date\MomentFormatConverter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class BasePickerType (to factorize DatePickerType and DateTimePickerType code.
 *
 * @author Hugo Briand <briand@ekino.com>
 */
abstract class BasePickerType extends AbstractType implements LocaleAwareInterface
{
    public function __construct(
        private MomentFormatConverter $formatConverter,
        protected TranslatorInterface $translator,
        protected string $locale
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setNormalizer(
            'format',
            function (Options $options, int | string $format) {
                if (isset($options['date_format']) && \is_string($options['date_format'])) {
                    return $options['date_format'];
                }

                if (\is_int($format)) {
                    $timeFormat = \IntlDateFormatter::NONE;
                    if (true === $options['dp_pick_time']) {
                        $timeFormat = true === $options['dp_use_seconds'] ?
                            DateTimeType::DEFAULT_TIME_FORMAT :
                            \IntlDateFormatter::SHORT;
                    }
                    $intlDateFormatter = new \IntlDateFormatter(
                        $this->locale,
                        $format,
                        $timeFormat,
                        null,
                        \IntlDateFormatter::GREGORIAN
                    );

                    return $intlDateFormatter->getPattern();
                }

                return $format;
            }
        );

        $resolver->setAllowedTypes('format', ['null', 'int', 'string']);
        $resolver->setAllowedTypes('widget', 'string');
        $resolver->setAllowedTypes('datepicker_use_button', 'bool');
        $resolver->setAllowedTypes('dp_pick_time', 'bool');
        $resolver->setAllowedTypes('dp_pick_date', 'bool');
        $resolver->setAllowedTypes('dp_use_current', 'bool');
        $resolver->setAllowedTypes('dp_min_date', ['null', 'string', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('dp_max_date', ['null', 'string', \DateTimeInterface::class]);
        $resolver->setAllowedTypes('dp_show_today', 'bool');
        $resolver->setAllowedTypes('dp_language', 'string');
        $resolver->setAllowedTypes('dp_default_date', ['null', 'string']);
        $resolver->setAllowedTypes('dp_disabled_dates', 'array');
        $resolver->setAllowedTypes('dp_enabled_dates', 'array');
        $resolver->setAllowedTypes('dp_icons', 'array');
        $resolver->setAllowedTypes('dp_use_strict', 'bool');
        $resolver->setAllowedTypes('dp_side_by_side', 'bool');
        $resolver->setAllowedTypes('dp_days_of_week_disabled', 'array');
        $resolver->setAllowedTypes('dp_collapse', 'bool');
        $resolver->setAllowedTypes('dp_calendar_weeks', 'bool');
        $resolver->setAllowedTypes('dp_view_mode', 'string');
        $resolver->setAllowedTypes('dp_min_view_mode', 'string');
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $format = $options['format'];

        // use seconds if it's allowed in format
        $options['dp_use_seconds'] = str_contains($format, 's');

        if ($options['dp_min_date'] instanceof \DateTimeInterface) {
            $options['dp_min_date'] = $this->formatObject($options['dp_min_date'], $format);
        }
        if ($options['dp_max_date'] instanceof \DateTimeInterface) {
            $options['dp_max_date'] = $this->formatObject($options['dp_max_date'], $format);
        }

        $view->vars['moment_format'] = $this->formatConverter->convert($format);

        $view->vars['type'] = 'text';

        $dpOptions = [];
        foreach ($options as $key => $value) {
            if (str_contains($key, 'dp_')) {
                // We remove 'dp_' and camelize the options names
                $dpKey = substr($key, 3);
                $dpKey = preg_replace_callback(
                    '/_([a-z])/',
                    static fn (array $c): string => strtoupper($c[1]),
                    $dpKey
                );

                $dpOptions[$dpKey] = $value;
            }
        }

        $view->vars['datepicker_use_button'] = isset($options['datepicker_use_button']) && true === $options['datepicker_use_button'];
        $view->vars['dp_options'] = $dpOptions;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Gets base default options for the date pickers.
     *
     * @return array<string, mixed>
     */
    protected function getCommonDefaults(): array
    {
        return [
            'widget' => 'single_text',
            'datepicker_use_button' => true,
            'dp_pick_time' => true,
            'dp_pick_date' => true,
            'dp_use_current' => true,
            'dp_min_date' => '1/1/1900',
            'dp_max_date' => null,
            'dp_show_today' => true,
            'dp_language' => $this->locale,
            'dp_default_date' => '',
            'dp_disabled_dates' => [],
            'dp_enabled_dates' => [],
            'dp_icons' => [
                'time' => 'fa fa-clock-o',
                'date' => 'fa fa-calendar',
                'up' => 'fa fa-chevron-up',
                'down' => 'fa fa-chevron-down',
            ],
            'dp_use_strict' => false,
            'dp_side_by_side' => false,
            'dp_days_of_week_disabled' => [],
            'dp_collapse' => true,
            'dp_calendar_weeks' => false,
            'dp_view_mode' => 'days',
            'dp_min_view_mode' => 'days',
        ];
    }

    private function formatObject(\DateTimeInterface $dateTime, string $format): string
    {
        $formatter = new \IntlDateFormatter($this->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE);
        $formatter->setPattern($format);

        $formatted = $formatter->format($dateTime);
        if (!\is_string($formatted)) {
            throw new \RuntimeException(sprintf('The format "%s" is invalid.', $format));
        }

        return $formatted;
    }
}
