<?php

namespace ObjectBG\TranslationBundle\Entity;

use Doctrine\ORM\EntityRepository;
use ObjectBG\TranslationBundle\Entity\Language as LanguageEntity;

class TranslationRepository extends EntityRepository
{
    /**
     * Return all translations for specified token
     * @param LanguageEntity $language
     * @param string $catalogue
     * @return <Translation>
     */
    public function getTranslations(LanguageEntity $language, $catalogue = "messages")
    {
        $query = $this->getEntityManager()->createQuery(
            "SELECT t,token FROM ObjectBGTranslationBundle:Translation t JOIN t.translationToken token WHERE t.language = :language AND token.catalogue = :catalogue"
        );
        $query->setParameter("language", $language);
        $query->setParameter("catalogue", $catalogue);
        $r = $query->getResult();

        return $r;
    }

    /**
     * @param $locale
     * @param string $catalogue
     * @return array
     */
    public function getTranslationsByLocale($locale, $catalogue = "messages")
    {
        $query = $this->getEntityManager()->createQuery(
            "SELECT t,token,language FROM ObjectBGTranslationBundle:Translation t JOIN t.translationToken token JOIN t.language language WHERE language.locale = :locale AND token.catalogue = :catalogue"
        );
        $query->setParameter("locale", $locale);
        $query->setParameter("catalogue", $catalogue);
        $r = $query->getResult();

        return $r;
    }

    /**
     * @param $locale
     * @return array
     */
    public function getAllTranslationsByLocale($locale)
    {
        $query = $this->getEntityManager()->createQuery(
            "SELECT translation,token,language FROM ObjectBGTranslationBundle:Translation translation JOIN translation.translationToken token JOIN translation.language language WHERE language.locale = :locale"
        );
        $query->setParameter("locale", $locale);
        $r = $query->getResult();

        return $r;
    }


    public function getTranslationByTokenAndLanguage(TranslationToken $translationToken, LanguageEntity $language)
    {

        $em = $this->getEntityManager();
        $dql = "SELECT t FROM ObjectBGTranslationBundle:Translation t  WHERE t.translationToken = :token AND t.language = :language";

        $exists = $em->createQuery($dql)
            ->setParameter('token', $translationToken)
            ->setParameter('language', $language)
            ->getOneOrNullResult();

        return $exists;
    }
}
