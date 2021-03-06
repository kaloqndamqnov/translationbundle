<?php

namespace ObjectBG\TranslationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="ObjectBG\TranslationBundle\Entity\TranslationTokenRepository")
 * @ORM\Table(name="translation_tokens",
 *       uniqueConstraints={@ORM\UniqueConstraint(columns={"token", "catalogue"})}
 * )
 * @UniqueEntity(fields={"token", "catalogue"}, message="This token already exists")
 */
class TranslationToken
{

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /** @ORM\Column(type="string", length=200) */
    private $token;

    /** @ORM\column(type="string", length=200) */
    private $catalogue;

    /**
     * @ORM\OneToMany(targetEntity="Translation", mappedBy="translationToken", cascade={"PERSIST", "REMOVE"})
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getToken()
    {
        return $this->token;
    }

    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getTranslations()
    {
        return $this->translations;
    }

    public function setTranslations($translations)
    {
        $this->translations = $translations;
    }

    /**
     * @return mixed
     */
    public function getCatalogue()
    {
        return $this->catalogue;
    }

    /**
     * @param mixed $catalogue
     */
    public function setCatalogue($catalogue)
    {
        $this->catalogue = $catalogue;
    }

    public function getTranslation(Language $Language)
    {
        return $this->getTranslations()->filter(
            function ($item) use ($Language) {
                return $item->getLanguage() == $Language;
            }
        )->first();
    }

    public function __toString()
    {
        return $this->token;
    }
}
