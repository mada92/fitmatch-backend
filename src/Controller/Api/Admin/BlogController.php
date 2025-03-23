<?php

namespace App\Controller\Api\Admin;

use App\Entity\BlogPost;
use App\Repository\BlogPostRepository;
use App\Service\FileUploader;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/blog')]
#[IsGranted('ROLE_ADMIN')]
class BlogController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private BlogPostRepository $blogPostRepository;
    private SlugGenerator $slugGenerator;
    private FileUploader $fileUploader;

    public function __construct(
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        BlogPostRepository $blogPostRepository,
        SlugGenerator $slugGenerator,
        FileUploader $fileUploader
    ) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->blogPostRepository = $blogPostRepository;
        $this->slugGenerator = $slugGenerator;
        $this->fileUploader = $fileUploader;
    }

    #[Route('', name: 'api_admin_blog_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(50, $request->query->getInt('limit', 10)));
        $search = $request->query->get('search', '');
        $published = $request->query->has('published') ? $request->query->getBoolean('published') : null;

        $result = $this->blogPostRepository->findBySearchPaginated($search, $published, $page, $limit);

        return $this->json([
            'posts' => $result['data'],
            'total' => $result['total'],
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($result['total'] / $limit)
        ], Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['blog:read', 'blog:list', 'user:read']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_blog_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $post = $this->blogPostRepository->find($id);

        if (!$post) {
            return $this->json(['message' => 'Blog post not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($post, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['blog:read', 'blog:detail', 'user:read']
        ]);
    }

    #[Route('', name: 'api_admin_blog_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $post = new BlogPost();
        $post->setTitle($data['title'] ?? '')
            ->setContent($data['content'] ?? '')
            ->setExcerpt($data['excerpt'] ?? null)
            ->setMetaTitle($data['metaTitle'] ?? null)
            ->setMetaDescription($data['metaDescription'] ?? null)
            ->setAuthor($this->getUser())
            ->setIsPublished($data['isPublished'] ?? false);

        if (isset($data['tags']) && is_array($data['tags'])) {
            $post->setTags($data['tags']);
        }

        // Generate slug from title
        $slug = $this->slugGenerator->generateSlug($data['title'] ?? '');
        $post->setSlug($slug);

        if (isset($data['featuredImage']) && is_array($data['featuredImage']) && isset($data['featuredImage']['data'])) {
            $imageData = $data['featuredImage']['data'];
            $filename = $this->fileUploader->uploadBase64Image($imageData, 'blog');
            $post->setFeaturedImage($filename);
        }

        $errors = $this->validator->validate($post);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $this->json($post, Response::HTTP_CREATED, [], [
            AbstractNormalizer::GROUPS => ['blog:read', 'blog:detail']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_blog_update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->find($id);

        if (!$post) {
            return $this->json(['message' => 'Blog post not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $post->setTitle($data['title']);

            // Update slug if title changes
            $slug = $this->slugGenerator->generateSlug($data['title']);
            $post->setSlug($slug);
        }

        if (isset($data['content'])) {
            $post->setContent($data['content']);
        }

        if (isset($data['excerpt'])) {
            $post->setExcerpt($data['excerpt']);
        }

        if (isset($data['metaTitle'])) {
            $post->setMetaTitle($data['metaTitle']);
        }

        if (isset($data['metaDescription'])) {
            $post->setMetaDescription($data['metaDescription']);
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            $post->setTags($data['tags']);
        }

        if (isset($data['isPublished'])) {
            $post->setIsPublished((bool) $data['isPublished']);
        }

        if (isset($data['featuredImage']) && is_array($data['featuredImage']) && isset($data['featuredImage']['data'])) {
            // Remove old image if exists
            if ($post->getFeaturedImage()) {
                $this->fileUploader->removeFile($post->getFeaturedImage(), 'blog');
            }

            $imageData = $data['featuredImage']['data'];
            $filename = $this->fileUploader->uploadBase64Image($imageData, 'blog');
            $post->setFeaturedImage($filename);
        }

        $errors = $this->validator->validate($post);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($post, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['blog:read', 'blog:detail']
        ]);
    }

    #[Route('/{id}', name: 'api_admin_blog_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $post = $this->blogPostRepository->find($id);

        if (!$post) {
            return $this->json(['message' => 'Blog post not found'], Response::HTTP_NOT_FOUND);
        }

        // Remove featured image if exists
        if ($post->getFeaturedImage()) {
            $this->fileUploader->removeFile($post->getFeaturedImage(), 'blog');
        }

        $this->entityManager->remove($post);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/publish', name: 'api_admin_blog_publish', methods: ['PATCH'])]
    public function publish(int $id): JsonResponse
    {
        $post = $this->blogPostRepository->find($id);

        if (!$post) {
            return $this->json(['message' => 'Blog post not found'], Response::HTTP_NOT_FOUND);
        }

        $post->setIsPublished(true);
        $post->setPublishedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json($post, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['blog:read']
        ]);
    }

    #[Route('/{id}/unpublish', name: 'api_admin_blog_unpublish', methods: ['PATCH'])]
    public function unpublish(int $id): JsonResponse
    {
        $post = $this->blogPostRepository->find($id);

        if (!$post) {
            return $this->json(['message' => 'Blog post not found'], Response::HTTP_NOT_FOUND);
        }

        $post->setIsPublished(false);

        $this->entityManager->flush();

        return $this->json($post, Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['blog:read']
        ]);
    }

    #[Route('/stats', name: 'api_admin_blog_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $totalPosts = $this->blogPostRepository->count([]);
        $publishedPosts = $this->blogPostRepository->count(['isPublished' => true]);
        $draftPosts = $this->blogPostRepository->count(['isPublished' => false]);

        $popularPosts = $this->blogPostRepository->findMostViewed(5);
        $recentPosts = $this->blogPostRepository->findBy([], ['createdAt' => 'DESC'], 5);

        return $this->json([
            'totalPosts' => $totalPosts,
            'publishedPosts' => $publishedPosts,
            'draftPosts' => $draftPosts,
            'popularPosts' => $popularPosts,
            'recentPosts' => $recentPosts
        ], Response::HTTP_OK, [], [
            AbstractNormalizer::GROUPS => ['blog:read', 'blog:list']
        ]);
    }
}