<?php

/*

SPDX-License-Identifier: MIT
SPDX-FileCopyrightText: 2026 Project Tick
SPDX-FileContributor: Project Tick

Copyright (c) 2026 Project Tick

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
of the Software, and to permit persons to whom the Software is furnished to do
so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $summary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $aboutContent = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $version = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $downloadUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $githubUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $gitlabUrl = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $features = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $productType = 'software'; // software, service, library, policy

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $productStatus = 'active'; // active, deprecated, archived

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $productVisibility = 'public'; // public, internal

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $productOwner = null; // user / org

    #[ORM\Column]
    private ?bool $allowWindows = false;

    #[ORM\Column]
    private ?bool $allowMacos = false;

    #[ORM\Column]
    private ?bool $allowLinux = false;

    #[ORM\Column]
    private ?bool $isSparkleEnabled = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $downloadContentWindows = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $downloadContentMacos = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $downloadContentLinux = null;

    #[ORM\Column]
    private ?bool $useHtmlWindows = false;

    #[ORM\Column]
    private ?bool $useHtmlMacos = false;

    #[ORM\Column]
    private ?bool $useHtmlLinux = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $landingPageContent = null;

    #[ORM\Column]
    private ?bool $useCustomLandingPage = false;

    #[ORM\Column]
    private ?bool $requiresLicense = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\ManyToOne(targetEntity: License::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?License $license = null;

    #[ORM\ManyToOne(targetEntity: SpdxLicense::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?SpdxLicense $spdxLicense = null;

    #[ORM\OneToMany(mappedBy: 'assignedProduct', targetEntity: UserLicense::class)]
    private $assignedUserLicenses;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ftpFolderName = 'ProjT-Launcher';

    public function __construct()
    {
        $this->updatedAt = new \DateTime();
        $this->assignedUserLicenses = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, UserLicense>
     */
    public function getAssignedUserLicenses(): \Doctrine\Common\Collections\Collection
    {
        return $this->assignedUserLicenses;
    }

    public function addAssignedUserLicense(UserLicense $userLicense): static
    {
        if (!$this->assignedUserLicenses->contains($userLicense)) {
            $this->assignedUserLicenses->add($userLicense);
            $userLicense->setAssignedProduct($this);
        }

        return $this;
    }

    public function removeAssignedUserLicense(UserLicense $userLicense): static
    {
        if ($this->assignedUserLicenses->removeElement($userLicense)) {
            // set the owning side to null (unless already changed)
            if ($userLicense->getAssignedProduct() === $this) {
                $userLicense->setAssignedProduct(null);
            }
        }

        return $this;
    }

    public function isRequiresLicense(): ?bool
    {
        return $this->requiresLicense;
    }

    public function setRequiresLicense(bool $requiresLicense): static
    {
        $this->requiresLicense = $requiresLicense;
        return $this;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(?string $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): static
    {
        $this->summary = $summary;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAboutContent(): ?string
    {
        return $this->aboutContent;
    }

    public function setAboutContent(?string $aboutContent): static
    {
        $this->aboutContent = $aboutContent;
        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;
        return $this;
    }

    public function getDownloadUrl(): ?string
    {
        return $this->downloadUrl;
    }

    public function setDownloadUrl(?string $downloadUrl): static
    {
        $this->downloadUrl = $downloadUrl;
        return $this;
    }

    public function getGithubUrl(): ?string
    {
        return $this->githubUrl;
    }

    public function setGithubUrl(?string $githubUrl): static
    {
        $this->githubUrl = $githubUrl;

        return $this;
    }

    public function getGitlabUrl(): ?string
    {
        return $this->gitlabUrl;
    }

    public function setGitlabUrl(?string $gitlabUrl): static
    {
        $this->gitlabUrl = $gitlabUrl;

        return $this;
    }

    public function getFeatures(): array
    {
        return $this->features ?? [];
    }

    public function setFeatures(?array $features): static
    {
        $this->features = $features;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getProductType(): ?string
    {
        return $this->productType;
    }

    public function setProductType(?string $productType): static
    {
        $this->productType = $productType;
        return $this;
    }

    public function getProductStatus(): ?string
    {
        return $this->productStatus;
    }

    public function setProductStatus(?string $productStatus): static
    {
        $this->productStatus = $productStatus;
        return $this;
    }

    public function getProductVisibility(): ?string
    {
        return $this->productVisibility;
    }

    public function setProductVisibility(?string $productVisibility): static
    {
        $this->productVisibility = $productVisibility;
        return $this;
    }

    public function getProductOwner(): ?string
    {
        return $this->productOwner;
    }

    public function setProductOwner(?string $productOwner): static
    {
        $this->productOwner = $productOwner;
        return $this;
    }

    public function isAllowWindows(): ?bool
    {
        return $this->allowWindows;
    }

    public function setAllowWindows(bool $allowWindows): static
    {
        $this->allowWindows = $allowWindows;
        return $this;
    }

    public function isAllowMacos(): ?bool
    {
        return $this->allowMacos;
    }

    public function setAllowMacos(bool $allowMacos): static
    {
        $this->allowMacos = $allowMacos;
        return $this;
    }

    public function isAllowLinux(): ?bool
    {
        return $this->allowLinux;
    }

    public function setAllowLinux(bool $allowLinux): static
    {
        $this->allowLinux = $allowLinux;
        return $this;
    }

    public function isSparkleEnabled(): ?bool
    {
        return $this->isSparkleEnabled;
    }

    public function setIsSparkleEnabled(bool $isSparkleEnabled): static
    {
        $this->isSparkleEnabled = $isSparkleEnabled;
        return $this;
    }

    public function getDownloadContentWindows(): ?string
    {
        return $this->downloadContentWindows;
    }

    public function setDownloadContentWindows(?string $downloadContentWindows): static
    {
        $this->downloadContentWindows = $downloadContentWindows;
        return $this;
    }

    public function getDownloadContentMacos(): ?string
    {
        return $this->downloadContentMacos;
    }

    public function setDownloadContentMacos(?string $downloadContentMacos): static
    {
        $this->downloadContentMacos = $downloadContentMacos;
        return $this;
    }

    public function getDownloadContentLinux(): ?string
    {
        return $this->downloadContentLinux;
    }

    public function setDownloadContentLinux(?string $downloadContentLinux): static
    {
        $this->downloadContentLinux = $downloadContentLinux;
        return $this;
    }

    public function isUseHtmlWindows(): ?bool
    {
        return $this->useHtmlWindows;
    }

    public function setUseHtmlWindows(bool $useHtmlWindows): static
    {
        $this->useHtmlWindows = $useHtmlWindows;
        return $this;
    }

    public function isUseHtmlMacos(): ?bool
    {
        return $this->useHtmlMacos;
    }

    public function setUseHtmlMacos(bool $useHtmlMacos): static
    {
        $this->useHtmlMacos = $useHtmlMacos;
        return $this;
    }

    public function isUseHtmlLinux(): ?bool
    {
        return $this->useHtmlLinux;
    }

    public function setUseHtmlLinux(bool $useHtmlLinux): static
    {
        $this->useHtmlLinux = $useHtmlLinux;
        return $this;
    }

    public function getLicense(): ?License
    {
        return $this->license;
    }

    public function setLicense(?License $license): static
    {
        $this->license = $license;

        return $this;
    }

    public function getSpdxLicense(): ?SpdxLicense
    {
        return $this->spdxLicense;
    }

    public function setSpdxLicense(?SpdxLicense $spdxLicense): static
    {
        $this->spdxLicense = $spdxLicense;

        return $this;
    }

    public function getLandingPageContent(): ?string
    {
        return $this->landingPageContent;
    }

    public function setLandingPageContent(?string $landingPageContent): static
    {
        $this->landingPageContent = $landingPageContent;
        return $this;
    }

    public function isUseCustomLandingPage(): ?bool
    {
        return $this->useCustomLandingPage;
    }

    public function setUseCustomLandingPage(bool $useCustomLandingPage): static
    {
        $this->useCustomLandingPage = $useCustomLandingPage;
        return $this;
    }

    public function getFtpFolderName(): ?string
    {
        return $this->ftpFolderName;
    }

    public function setFtpFolderName(?string $ftpFolderName): static
    {
        $this->ftpFolderName = $ftpFolderName;
        return $this;
    }
}
