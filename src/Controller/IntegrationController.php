<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\GitHubIntegrationService;
use App\Service\GitLabIntegrationService;
use App\Service\GoogleIntegrationService;
use App\Service\KeycloakBrokerService;
use App\Service\MicrosoftXboxService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/integration')]
#[IsGranted('ROLE_USER')]
class IntegrationController extends AbstractController
{
    public function __construct(
        private KeycloakBrokerService $brokerService,
        private MicrosoftXboxService $xboxService,
        private GitHubIntegrationService $githubService,
        private GitLabIntegrationService $gitlabService,
        private GoogleIntegrationService $googleService,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Get linked providers for the current user.
     */
    #[Route('/linked-providers', name: 'api_integration_linked_providers', methods: ['GET'])]
    public function linkedProviders(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $keycloakId = $user->getKeycloakId();

        if (!$keycloakId) {
            return $this->json(['providers' => []], Response::HTTP_OK);
        }

        $linked = $this->brokerService->getLinkedProviders($keycloakId);

        return $this->json(['providers' => $linked]);
    }

    /**
     * Get the current user's GitHub profile via broker token.
     */
    #[Route('/github/profile', name: 'api_integration_github_profile', methods: ['GET'])]
    public function githubProfile(): JsonResponse
    {
        $token = $this->getBrokerTokenOrFail('github');
        if ($token instanceof JsonResponse) {
            return $token;
        }

        return $this->json($this->githubService->getUserProfile($token));
    }

    /**
     * Get the current user's GitHub repos via broker token.
     */
    #[Route('/github/repos', name: 'api_integration_github_repos', methods: ['GET'])]
    public function githubRepos(): JsonResponse
    {
        $token = $this->getBrokerTokenOrFail('github');
        if ($token instanceof JsonResponse) {
            return $token;
        }

        return $this->json($this->githubService->getUserRepos($token));
    }

    /**
     * Get the current user's GitLab profile via broker token.
     */
    #[Route('/gitlab/profile', name: 'api_integration_gitlab_profile', methods: ['GET'])]
    public function gitlabProfile(): JsonResponse
    {
        $token = $this->getBrokerTokenOrFail('gitlab');
        if ($token instanceof JsonResponse) {
            return $token;
        }

        return $this->json($this->gitlabService->getUserProfile($token));
    }

    /**
     * Get the current user's GitLab projects via broker token.
     */
    #[Route('/gitlab/projects', name: 'api_integration_gitlab_projects', methods: ['GET'])]
    public function gitlabProjects(): JsonResponse
    {
        $token = $this->getBrokerTokenOrFail('gitlab');
        if ($token instanceof JsonResponse) {
            return $token;
        }

        return $this->json($this->gitlabService->getUserProjects($token));
    }

    /**
     * Authenticate Minecraft via Microsoft broker token → Xbox → XSTS → MC profile.
     * Stores the result on the user entity.
     */
    #[Route('/minecraft/authenticate', name: 'api_integration_minecraft_auth', methods: ['POST'])]
    public function minecraftAuthenticate(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('minecraft_auth', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $token = $this->getBrokerTokenOrFail('microsoft');
        if ($token instanceof JsonResponse) {
            return $token;
        }

        try {
            $result = $this->xboxService->authenticateMinecraft($token);

            /** @var User $user */
            $user = $this->getUser();
            $user->setMinecraftAccessToken($result['minecraft_token']);
            $user->setMinecraftUsername($result['username']);
            $user->setMinecraftUuid($result['uuid']);
            $this->em->flush();

            return $this->json([
                'username' => $result['username'],
                'uuid' => $result['uuid'],
                'owns_game' => $result['owns_game'],
            ]);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => 'Minecraft authentication failed. Ensure your Microsoft account has XboxLive.signin scope.'], Response::HTTP_BAD_GATEWAY);
        }
    }

    /**
     * Get Google profile via broker token.
     */
    #[Route('/google/profile', name: 'api_integration_google_profile', methods: ['GET'])]
    public function googleProfile(): JsonResponse
    {
        $token = $this->getBrokerTokenOrFail('google');
        if ($token instanceof JsonResponse) {
            return $token;
        }

        return $this->json($this->googleService->getUserProfile($token));
    }

    /**
     * Helper: get broker token or return error response.
     */
    private function getBrokerTokenOrFail(string $provider): string|JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $keycloakId = $user->getKeycloakId();

        if (!$keycloakId) {
            return $this->json(['error' => 'No Keycloak account linked'], Response::HTTP_BAD_REQUEST);
        }

        $token = $this->brokerService->getBrokerToken($keycloakId, $provider);

        if (!$token) {
            return $this->json(
                ['error' => sprintf('Provider "%s" not linked or token unavailable. Link it at id.projecttick.net.', $provider)],
                Response::HTTP_NOT_FOUND
            );
        }

        return $token;
    }
}
