<?php



namespace App\Service;

use App\Repository\ZoneRepository;
use App\Repository\ProductLineRepository;
use App\Repository\CategoryRepository;
use App\Repository\ButtonRepository;
use App\Repository\UploadRepository;
use Doctrine\ORM\EntityManagerInterface;

class EntityDeletionService
{
    private $em;
    private $zoneRepository;
    private $productLineRepository;
    private $categoryRepository;
    private $buttonRepository;
    private $uploadRepository;

    public function __construct(
        EntityManagerInterface $em,
        ZoneRepository $zoneRepository,
        ProductLineRepository $productLineRepository,
        CategoryRepository $categoryRepository,
        ButtonRepository $buttonRepository,
        UploadRepository $uploadRepository
    ) {
        $this->em = $em;
        $this->zoneRepository = $zoneRepository;
        $this->productLineRepository = $productLineRepository;
        $this->categoryRepository = $categoryRepository;
        $this->buttonRepository = $buttonRepository;
        $this->uploadRepository = $uploadRepository;
    }

    public function deleteEntity(string $entityType, int $id): bool
    {
        $repository = null;
        switch ($entityType) {
            case 'zone':
                $repository = $this->zoneRepository;
                break;
            case 'productline':
                $repository = $this->productLineRepository;
                break;
                // Add other cases for other entity types
            case 'category':
                $repository = $this->categoryRepository;
                break;
            case 'button':
                $repository = $this->buttonRepository;
                break;
            case 'upload':
                $repository = $this->uploadRepository;
                break;
        }

        if (!$repository) {
            return false;
        }

        $entity = $repository->find($id);
        if (!$entity) {
            return false;
        }

        // Add deletion logic for related entities
        if ($entityType === 'zone') {
            foreach ($entity->getProductLines() as $productLine) {
                $this->deleteEntity('productline', $productLine->getId());
            }
            foreach ($productLine->getCategories() as $category) {
                $this->deleteEntity('category', $category->getId());
            }
            foreach ($category->getButtons() as $button) {
                $this->deleteEntity('button', $button->getId());
            }
        } elseif ($entityType === 'productline') {
            foreach ($entity->getCategories() as $category) {
                $this->deleteEntity('category', $category->getId());
            }
            foreach ($category->getButtons() as $button) {
                $this->deleteEntity('button', $button->getId());
            }
        } elseif ($entityType === 'category') {
            foreach ($entity->getButtons() as $button) {
                $this->deleteEntity('button', $button->getId());
            }
        }
        //  elseif ($entityType === 'button') { 
        //     foreach ($entity->getUploads() as $upload) {
        //         $this->deleteEntity('upload', $upload->getId());
        //     }
        // Add deletion logic for categories
        // Continue with other entity types and their related entities

        $this->em->remove($entity);
        $this->em->flush();

        return true;
    }
}