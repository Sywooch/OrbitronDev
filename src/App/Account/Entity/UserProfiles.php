<?php

namespace App\Account\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\OneToMany;
use Doctrine\ORM\Mapping\OneToOne;
use Doctrine\ORM\Mapping\Table;

/**
 * @Entity
 * @Table(name="user_profiles")
 */
class UserProfiles
{
    /**
     * @var int
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     */
    protected $id;

    /**
     * @var \App\Account\Entity\User
     * @OneToOne(targetEntity="User", inversedBy="profile")
     * @JoinColumn(name="id", referencedColumnName="id", nullable=false)
     */
    protected $user;

    /**
     * @var null|string
     * @Column(type="string", nullable=true)
     */
    protected $name;

    /**
     * @var null|string
     * @Column(type="string", nullable=true)
     */
    protected $surname;

    /**
     * @var null|int
     * @Column(type="smallint", nullable=true)
     */
    protected $gender;

    /**
     * @var null|\DateTime
     * @Column(type="date", nullable=true)
     */
    protected $birthday;

    /**
     * @var null|string
     * @Column(type="string", nullable=true)
     */
    protected $website;

    /**
     * @var null|string
     * @Column(type="string", nullable=true)
     */
    protected $picture;

    /**
     * @var null|int
     * @Column(type="integer", nullable=true)
     */
    protected $active_address;

    /**
     * @var \Doctrine\Common\Collections\Collection
     * @OneToMany(targetEntity="UserAddress", mappedBy="userProfile", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $addresses;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return \App\Account\Entity\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param \App\Account\Entity\User $user
     *
     * @return $this
     */
    public function setUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param null|string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSurname()
    {
        return $this->surname;
    }

    /**
     * @param null|string $surname
     *
     * @return $this
     */
    public function setSurname($surname)
    {
        $this->surname = $surname;

        return $this;
    }

    /**
     * @return null|int
     */
    public function getGender()
    {
        return $this->gender;
    }

    /**
     * @param null|int $gender
     *
     * @return $this
     */
    public function setGender($gender)
    {
        $this->gender = $gender;

        return $this;
    }

    /**
     * @return null|\DateTime
     */
    public function getBirthday()
    {
        return $this->birthday;
    }

    /**
     * @param null|\DateTime $birthday
     *
     * @return $this
     */
    public function setBirthday($birthday)
    {
        $this->birthday = $birthday;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getWebsite()
    {
        return $this->website;
    }

    /**
     * @param null|string $website
     *
     * @return $this
     */
    public function setWebsite($website)
    {
        $this->website = $website;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getPicture()
    {
        return $this->picture;
    }

    /**
     * @param null|string $picture
     *
     * @return $this
     */
    public function setPicture($picture)
    {
        $this->picture = $picture;

        return $this;
    }

    /**
     * @return null|int
     */
    public function getActiveAddress()
    {
        return $this->active_address;
    }

    /**
     * @param null|int $activeAddress
     *
     * @return $this
     */
    public function setActiveAddress($activeAddress)
    {
        $this->active_address = $activeAddress;

        return $this;
    }

    /**
     * @return \App\Account\Entity\UserAddress[]
     */
    public function getAddresses()
    {
        return $this->addresses->toArray();
    }

    /**
     * @param \App\Account\Entity\UserAddress $address
     *
     * @return $this
     */
    public function addAddress(UserAddress $address)
    {
        $this->addresses->add($address);
        $address->setUserProfile($this);

        return $this;
    }

    /**
     * @param \App\Account\Entity\UserAddress $address
     *
     * @return $this
     */
    public function removeAddress(UserAddress $address)
    {
        if ($this->addresses->contains($address)) {
            $this->addresses->removeElement($address);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return array(
            'user_id'        => $this->id,
            'name'           => $this->name,
            'surname'        => $this->surname,
            'gender'         => $this->gender,
            'birthday'       => $this->birthday,
            'website'        => $this->website,
            'picture'        => $this->picture,
            'active_address' => $this->active_address,
            'addresses'      => $this->addresses->toArray(),
        );
    }
}
