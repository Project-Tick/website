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

use App\Repository\PublicCredentialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\PublicKeyCredentialSource;

#[ORM\Entity(repositoryClass: PublicCredentialRepository::class)]
class PublicCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'publicCredentials')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $publicKeyCredentialId = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $publicKey = null;

    #[ORM\Column]
    private ?int $signCount = 0;

    #[ORM\Column(length: 255)]
    private ?string $userHandle = null;

    #[ORM\Column(type: Types::JSON)]
    private array $transports = [];

    #[ORM\Column(length: 255)]
    private ?string $attestationType = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $credentialId = null;

    #[ORM\Column(type: Types::JSON)]
    private array $trustPath = [];

    #[ORM\Column(type: Types::GUID)]
    private ?string $aaguid = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPublicKeyCredentialId(): ?string
    {
        return $this->publicKeyCredentialId;
    }

    public function setPublicKeyCredentialId(string $publicKeyCredentialId): static
    {
        $this->publicKeyCredentialId = $publicKeyCredentialId;
        return $this;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): static
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    public function getSignCount(): ?int
    {
        return $this->signCount;
    }

    public function setSignCount(int $signCount): static
    {
        $this->signCount = $signCount;
        return $this;
    }

    public function getUserHandle(): ?string
    {
        return $this->userHandle;
    }

    public function setUserHandle(string $userHandle): static
    {
        $this->userHandle = $userHandle;
        return $this;
    }

    public function getTransports(): array
    {
        return $this->transports;
    }

    public function setTransports(array $transports): static
    {
        $this->transports = $transports;
        return $this;
    }

    public function getAttestationType(): ?string
    {
        return $this->attestationType;
    }

    public function setAttestationType(string $attestationType): static
    {
        $this->attestationType = $attestationType;
        return $this;
    }

    public function getCredentialId(): ?string
    {
        return $this->credentialId;
    }

    public function setCredentialId(string $credentialId): static
    {
        $this->credentialId = $credentialId;
        return $this;
    }

    public function getTrustPath(): array
    {
        return $this->trustPath;
    }

    public function setTrustPath(array $trustPath): static
    {
        $this->trustPath = $trustPath;
        return $this;
    }

    public function getAaguid(): ?string
    {
        return $this->aaguid;
    }

    public function setAaguid(string $aaguid): static
    {
        $this->aaguid = $aaguid;
        return $this;
    }
}
