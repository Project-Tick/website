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

use Doctrine\ORM\Mapping as ORM;

/**
 * Bu entity Uptime Kuma tablosuyla eşleşecek ancak Symfony tarafından yönetilecek.
 */
#[ORM\Entity]
class Monitor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column]
    private int $active = 1;

    #[ORM\Column(length: 20)]
    private string $type = 'http';

    #[ORM\Column(name: '`interval`')]
    private int $interval = 60;

    #[ORM\Column]
    private int $retryInterval = 60;

    #[ORM\Column]
    private int $maxRetries = 0;

    #[ORM\Column]
    private int $weight = 1000;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $method = 'GET';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $headers = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dns_resolve_type = 'A';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dns_resolve_server = '1.1.1.1';

    #[ORM\Column(nullable: true)]
    private ?int $port = null;

    #[ORM\Column]
    private int $expiryNotification = 0;

    #[ORM\Column]
    private int $ignoreTls = 0;

    #[ORM\Column]
    private int $upsideDown = 0; // If checked, status 200 means DOWN

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active ? 1 : 0;
        return $this;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function setInterval(int $interval): self
    {
        $this->interval = $interval;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): self
    {
        $this->method = $method;
        return $this;
    }

    public function isIgnoreTls(): bool
    {
        return (bool) $this->ignoreTls;
    }

    public function setIgnoreTls(bool $ignoreTls): self
    {
        $this->ignoreTls = $ignoreTls ? 1 : 0;
        return $this;
    }

    public function isUpsideDown(): bool
    {
        return (bool) $this->upsideDown;
    }

    public function setUpsideDown(bool $upsideDown): self
    {
        $this->upsideDown = $upsideDown ? 1 : 0;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
}
