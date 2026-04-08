<?php

namespace App\Repository;

use App\Entity\PublicCredential;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\Bundle\Repository\PublicKeyCredentialSourceRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\Bundle\Repository\CanSaveCredentialSource;

/**
 * @extends ServiceEntityRepository<PublicCredential>
 */
class PublicCredentialRepository extends ServiceEntityRepository implements PublicKeyCredentialSourceRepositoryInterface, PublicKeyCredentialUserEntityRepositoryInterface, CanSaveCredentialSource
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PublicCredential::class);
    }

    // --- PublicKeyCredentialSourceRepositoryInterface Implementation ---

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $data = $this->findOneBy(['publicKeyCredentialId' => base64_encode($publicKeyCredentialId)]);
        if (!$data) return null;

        return $this->createSourceFromEntity($data);
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $sources = [];
        $entities = $this->findBy(['userHandle' => $publicKeyCredentialUserEntity->getId()]);
        foreach ($entities as $entity) {
            $sources[] = $this->createSourceFromEntity($entity);
        }
        return $sources;
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $entity = $this->findOneBy(['publicKeyCredentialId' => base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId())]) ?? new PublicCredential();
        
        // Find user by handle
        $user = $this->getEntityManager()->getRepository(User::class)->find($publicKeyCredentialSource->getUserHandle());
        
        $entity->setUser($user);
        $entity->setPublicKeyCredentialId(base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId()));
        $entity->setPublicKey(base64_encode($publicKeyCredentialSource->getCredentialPublicKey()));
        $entity->setSignCount($publicKeyCredentialSource->getCounter());
        $entity->setUserHandle($publicKeyCredentialSource->getUserHandle());
        $entity->setTransports($publicKeyCredentialSource->getTransports());
        $entity->setAttestationType($publicKeyCredentialSource->getAttestationType());
        $entity->setCredentialId(base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId()));
        $entity->setTrustPath($publicKeyCredentialSource->getTrustPath()->jsonSerialize());
        $entity->setAaguid($publicKeyCredentialSource->getAaguid()->toString());

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    // --- PublicKeyCredentialUserEntityRepositoryInterface Implementation ---

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->getEntityManager()->getRepository(User::class)->findOneBy(['email' => $username]);
        if (!$user) return null;

        return new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            (string) $user->getId(),
            $user->getUsername() ?? $user->getEmail(),
            null
        );
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $user = $this->getEntityManager()->getRepository(User::class)->find($userHandle);
        if (!$user) return null;

        return new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            (string) $user->getId(),
            $user->getUsername() ?? $user->getEmail(),
            null
        );
    }

    // --- Helper ---

    private function createSourceFromEntity(PublicCredential $entity): PublicKeyCredentialSource
    {
        return new PublicKeyCredentialSource(
            base64_decode($entity->getPublicKeyCredentialId()),
            $entity->getAttestationType(),
            $entity->getTransports(),
            $entity->getAttestationType(),
            \Webauthn\TrustPath\EmptyTrustPath::create(),
            Uuid::fromString($entity->getAaguid()),
            base64_decode($entity->getPublicKey()),
            $entity->getUserHandle(),
            $entity->getSignCount()
        );
    }
}
