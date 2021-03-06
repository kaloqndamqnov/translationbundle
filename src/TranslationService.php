<?php

namespace ObjectBG\TranslationBundle;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Util\ClassUtils;
use ObjectBG\TranslationBundle\Entity\Language;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Translation\TranslatorInterface;

class TranslationService
{
    private $typeGuesser;

    /**
     *
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     *
     * @var Reader
     */
    private $annotationReader;

    /**
     *
     * @var Container
     */
    private $container;

    /**
     *
     * @var Language[]
     */
    private $languages;

    /**
     *
     * @var TranslatorInterface
     */
    private $translator;

    /**
     *
     * @var Request
     */
    private $request;

    /**
     *
     * @var PropertyAccessor
     */
    private $propertyAccess;

    /**
     *
     * @param \Symfony\Component\DependencyInjection\Container $container
     * @param \Symfony\Component\Form\FormRegistry $formRegistry
     * @param \Doctrine\Common\Persistence\ManagerRegistry $managerRegistry
     * @param \Doctrine\Common\Annotations\Reader $Reader
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->typeGuesser = $container->get('form.registry')->getTypeGuesser();
        $this->managerRegistry = $container->get('doctrine');
        $this->annotationReader = $container->get('annotation_reader');
        $this->translator = $container->get('translator.default');
        $this->request = $container->get('request_stack')->getCurrentRequest();
        $this->propertyAccess = PropertyAccess::createPropertyAccessor();
    }

    public function getTranslation($entity, $language)
    {
        $Translations = $this->propertyAccess->getValue($entity, $this->getTranslationsField($entity));

        $translationService = $this;
        $PropertyAccess = $this->propertyAccess;
        $Translation = $Translations->filter(
            function ($item) use ($translationService, $language, $PropertyAccess) {
                $TranslationLanguage = $PropertyAccess->getValue($item, $translationService->getLanguageField($item));

                return $language instanceof Language ? ($TranslationLanguage == $language) : ($TranslationLanguage->getLocale(
                    ) == $language);
            }
        )->first();

        return $Translation;
    }

    public function getLanguages()
    {
        if (isset($this->languages) == false) {
            $LanguageClass = 'ObjectBG\TranslationBundle\Entity\Language';
            $manager = $this->managerRegistry->getManagerForClass($LanguageClass);
            $this->languages = $manager->getRepository($LanguageClass)->findAll();
            $this->languages = new \Doctrine\Common\Collections\ArrayCollection($this->languages);
        }

        return $this->languages;
    }

    public function getCurrentLanguage()
    {
        $CurrentLocale = $this->request->get('_locale');
        if (!$CurrentLocale) {
            $CurrentLocale = $this->request->getLocale();
        }
        if (!$CurrentLocale) {
            $CurrentLocale = $this->translator->getLocale();
        }

        return $this->getLanguages()->filter(
            function (Language $Lang) use ($CurrentLocale) {
                return $Lang->getLocale() == $CurrentLocale;
            }
        )->first();
    }

    public function getFallbackLocales()
    {
        return $this->translator->getFallbackLocales();
    }

    public function getLocales()
    {
        $locales = array();
        foreach ($this->getLanguages() as $lang) {
            $locales[$lang->getLocale()] = $lang->getName();
        }

        return $locales;
    }

    public function getDefaultLocale()
    {
        return $this->container->getParameter('locale');
    }

    public function getRequiredLocales()
    {
        return array();
//        return array($this->Container->getParameter('locale'));
    }

    public function getTranslationClass($translatableClass)
    {
        $translatableClass = ClassUtils::getRealClass($translatableClass);

        if ($manager = $this->managerRegistry->getManagerForClass($translatableClass)) {
            $metadataClass = $manager->getMetadataFactory()->getMetadataFor($translatableClass);
            foreach ($metadataClass->reflFields as $Field => $Reflection) {
                $Annotation = $this->annotationReader->getPropertyAnnotation(
                    $Reflection,
                    'ObjectBG\TranslationBundle\Annotation\Translations'
                );
                if ($Annotation) {
                    $AssocMapping = $metadataClass->associationMappings[$Field];

                    return $AssocMapping['targetEntity'];
                }
            }
        }
        throw Exception\InvalidArgumentException::missingTranslations($translatableClass);
    }

    /**
     *
     * @param string $translationClass
     * @param array $exclude
     * @return array
     */
    protected function getTranslationFields($translationClass, array $exclude = array())
    {
        $fields = array();
        $translationClass = ClassUtils::getRealClass($translationClass);
        $manager = $this->managerRegistry->getManagerForClass($translationClass);

        if ($manager) {
            $metadataClass = $manager->getMetadataFactory()->getMetadataFor($translationClass);

            foreach ($metadataClass->reflFields as $Field => $Reflection) {
                $Annotation = $this->annotationReader->getPropertyAnnotation(
                    $Reflection,
                    'ObjectBG\TranslationBundle\Annotation\Column'
                );
                if ($Annotation) {
                    $fields[] = $Field;
                }
            }
        }

        return $fields;
    }

    protected function getFieldByAnnotation($class, $Annotation)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $class = ClassUtils::getRealClass($class);
        $ReflectionClass = new \ReflectionClass($class);

        foreach ($ReflectionClass->getProperties() as $ReflectionProperty) {
            $Found = $this->annotationReader->getPropertyAnnotation($ReflectionProperty, $Annotation);
            if ($Found) {
                return $ReflectionProperty->getName();
            }
        }

        throw Exception\InvalidArgumentException::missingRequiredAnnotation($class, $Annotation);
    }

    public function getLanguageField($translationClass)
    {
        return $this->getFieldByAnnotation($translationClass, 'ObjectBG\TranslationBundle\Annotation\Language');
    }

    public function getTranslatableField($translationClass)
    {
        return $this->getFieldByAnnotation($translationClass, 'ObjectBG\TranslationBundle\Annotation\Translatable');
    }

    public function getTranslationsField($translatableClass)
    {
        return $this->getFieldByAnnotation($translatableClass, 'ObjectBG\TranslationBundle\Annotation\Translations');
    }

    public function getCurrentTranslationField($translatableClass)
    {
        return $this->getFieldByAnnotation(
            $translatableClass,
            'ObjectBG\TranslationBundle\Annotation\CurrentTranslation'
        );
    }

    public function getLanguageByLocale($locale)
    {
        return $this->getLanguages()->filter(
            function (Language $Lang) use ($locale) {
                return $Lang->getLocale() == $locale;
            }
        )->first();
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldsOptions($class, $options)
    {
        $fieldsOptions = array();

        foreach ($this->getFieldsList($options, $class) as $field) {
            $fieldOptions = isset($options['fields'][$field]) ? $options['fields'][$field] : array();

            if (!isset($fieldOptions['display']) || $fieldOptions['display']) {
                $fieldOptions = $this->guessMissingFieldOptions($this->typeGuesser, $class, $field, $fieldOptions);

                // Custom options by locale
                if (isset($fieldOptions['locale_options'])) {
                    $localesFieldOptions = $fieldOptions['locale_options'];
                    unset($fieldOptions['locale_options']);

                    foreach ($options['locales'] as $locale => $name) {
                        $localeFieldOptions = isset($localesFieldOptions[$locale]) ? $localesFieldOptions[$locale] : array();
                        if (!isset($localeFieldOptions['display']) || $localeFieldOptions['display']) {
                            $fieldsOptions[$locale][$field] = $localeFieldOptions + $fieldOptions;
                        }
                    }

                    // General options for all locales
                } else {
                    foreach ($options['locales'] as $locale => $name) {
                        $fieldsOptions[$locale][$field] = $fieldOptions;
                    }
                }
            }
        }

        return $fieldsOptions;
    }

    /**
     * Combine formFields with translationFields. (Useful for upload field)
     */
    private function getFieldsList($options, $class)
    {
        $formFields = array_keys($options['fields']);

        if (count($formFields) != 0) {
            // Check existing
            foreach ($formFields as $field) {
                if (!property_exists($class, $field)) {
                    throw new \Exception("Field '" . $field . "' doesn't exist in " . $class);
                }
            }

            return $formFields;
        }

        return array_unique(array_merge($formFields, $this->getTranslationFields($class, $options['exclude_fields'])));
    }

    /**
     * {@inheritdoc}
     */
    public function getFormsOptions($options)
    {
        $formsOptions = array();

        // Current options
        $formOptions = $options['form_options'];

        // Custom options by locale
        if (isset($formOptions['locale_options'])) {
            $localesFormOptions = $formOptions['locale_options'];
            unset($formOptions['locale_options']);

            foreach ($options['locales'] as $locale) {
                $localeFormOptions = isset($localesFormOptions[$locale]) ? $localesFormOptions[$locale] : array();
                if (!isset($localeFormOptions['display']) || $localeFormOptions['display']) {
                    $formsOptions[$locale] = $localeFormOptions + $formOptions;
                }
            }

            // General options for all locales
        } else {
            foreach ($options['locales'] as $locale) {
                $formsOptions[$locale] = $formOptions;
            }
        }

        return $formsOptions;
    }

    /**
     * {@inheritdoc}
     */
    public function guessMissingFieldOptions($guesser, $class, $property, $options)
    {
        if (!isset($options['field_type']) && ($typeGuess = $guesser->guessType($class, $property))) {
            $options['field_type'] = $typeGuess->getType();
        }

        return $options;
    }
}
