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

use App\Repository\LicenseComparisonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LicenseComparisonRepository::class)]
class LicenseComparison
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $feature = null;

    #[ORM\Column(length: 255)]
    private ?string $mitApache = null;

    #[ORM\Column(length: 255)]
    private ?string $gnuGpl = null;

    #[ORM\Column(length: 255)]
    private ?string $projectTick = null;

    #[ORM\Column]
    private ?int $displayOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFeature(): ?string
    {
        return $this->feature;
    }

    public function setFeature(string $feature): static
    {
        $this->feature = $feature;
        return $this;
    }

    public function getMitApache(): ?string
    {
        return $this->mitApache;
    }

    public function setMitApache(string $mitApache): static
    {
        $this->mitApache = $mitApache;
        return $this;
    }

    public function getGnuGpl(): ?string
    {
        return $this->gnuGpl;
    }

    public function setGnuGpl(string $gnuGpl): static
    {
        $this->gnuGpl = $gnuGpl;
        return $this;
    }

    public function getProjectTick(): ?string
    {
        return $this->projectTick;
    }

    public function setProjectTick(string $projectTick): static
    {
        $this->projectTick = $projectTick;
        return $this;
    }

    public function getDisplayOrder(): ?int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): static
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }
}
