<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


use App\Repository\ButtonRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductLineRepository;
use App\Repository\ZoneRepository;
use App\Repository\UploadRepository;
use App\Repository\OldUploadRepository;
use App\Repository\IncidentRepository;

use App\Service\FolderCreationService;

class ViewsModificationService extends AbstractController
{
    private $em;
    private $projectDir;
    private $logger;

    private $zoneRepository;
    private $productLineRepository;
    private $categoryRepository;
    private $buttonRepository;
    private $uploadRepository;
    private $oldUploadRepository;
    private $incidentRepository;

    private $folderCreationService;

    public function __construct(
        EntityManagerInterface $em,
        ParameterBagInterface $params,
        LoggerInterface $logger,

        ZoneRepository $zoneRepository,
        ProductLineRepository $productLineRepository,
        CategoryRepository $categoryRepository,
        ButtonRepository $buttonRepository,
        UploadRepository $uploadRepository,
        OldUploadRepository $oldUploadRepository,
        IncidentRepository $incidentRepository,

        FolderCreationService $folderCreationService
    ) {
        $this->em                       = $em;
        $this->projectDir               = $params->get('kernel.project_dir');
        $this->logger                   = $logger;

        $this->zoneRepository           = $zoneRepository;
        $this->productLineRepository    = $productLineRepository;
        $this->categoryRepository       = $categoryRepository;
        $this->buttonRepository         = $buttonRepository;
        $this->uploadRepository         = $uploadRepository;
        $this->oldUploadRepository      = $oldUploadRepository;
        $this->incidentRepository       = $incidentRepository;

        $this->folderCreationService    = $folderCreationService;
    }

    public function updateTheUpdatingOfTheSortOrder()
    {
        $zones = $this->zoneRepository->findAll();

        $zoneSortorder = 0;
        foreach ($zones as $zone) {
            $zone->setSortOrder(++$zoneSortorder);
            $this->em->persist($zone);
            $productLines = $zone->getProductLines();

            $productLineSortorder = 0;
            foreach ($productLines as $productLine) {
                $productLine->setSortOrder(++$productLineSortorder);
                $this->em->persist($productLine);
                $categories = $productLine->getCategories();

                $categoriesSortorder = 0;
                foreach ($categories as $category) {
                    $category->setSortOrder(++$categoriesSortorder);
                    $this->em->persist($category);
                    $buttons = $category->getButtons();

                    $buttonsSortorder = 0;
                    foreach ($buttons as $button) {
                        $button->setSortOrder(++$buttonsSortorder);
                        $this->em->persist($button);
                    }
                }
            }
        }
        $this->em->flush();
    }
    // 
    // 
    //     
    public function extractComponentsFromKey($key)
    {
        // Split the string by underscores
        $parts = explode('_', $key);

        // Check if there are enough parts and if the ID part is numeric
        if (count($parts) == 3 && is_numeric($parts[1])) {
            return [
                'entity' => $parts[0],
                'id'     => intval($parts[1]),
                'field'  => $parts[2]
            ];
        }
        return null;
    }
    // 
    // 
    //     
    public function defineEntityType($entityType)
    {
        $this->logger->info('defineEntityType: entityType: ' . $entityType);
        $repository = null;
        switch ($entityType) {
            case 'zone':
                $repository = $this->zoneRepository;
                break;
            case 'productLine':
                $repository = $this->productLineRepository;
                break;
            case 'category':
                $repository = $this->categoryRepository;
                break;
            case 'button':
                $repository = $this->buttonRepository;
                break;
        }
        // If the repository is not found or the entity is not found in the database, return false
        if (!$repository) {
            return false;
        }
        return $repository;
    }
    // 
    // 
    //     
    public function defineOriginalValue($entity, $field)
    {
        $OriginalValue = null;
        switch ($field) {
            case 'sortOrder':
                $OriginalValue = $entity->getSortOrder();
                break;
            case 'name':
                $OriginalValue = $entity->getName();
                break;
        }
        return $OriginalValue;
    }
    // 
    // 
    //     
    public function updateEntity($entityType, $entity, $field, $newValue, $originalValue)
    {
        $this->logger->info('updateEntity: entityType: ' . $entityType);
        $this->logger->info('updateEntity: entityName: ' . $entity->getName());
        $this->logger->info('updateEntity: field: ' . $field);
        $this->logger->info('updateEntity: newValue: ' . $newValue);
        $this->logger->info('updateEntity: originalValue: ' . $originalValue);

        $entityId = $entity->getId();
        switch ($field) {
            case 'sortOrder':
                $this->updateNameAndFolderByParentEntity($entityType, $entityId, $newValue, $originalValue, $field);
                break;
            case 'name':
                $this->updateNameAndFolderByParentEntity($entityType, $entityId, $newValue, $originalValue, $field);
                $entity->setName($newValue);
                $this->folderCreationService->updateFolderStructureAndName($originalValue, $newValue);
                break;
        }
        $this->em->persist($entity);
        $this->em->flush();
    }
    // 
    // 
    //     
    public function updateSortOrders($otherEntities, $entity, $newValue, $originalValue)
    {
        $this->logger->info('entity name' . $entity->getName());
        $this->logger->info('entity id' . $entity->getId());
        $this->logger->info('newValue: ' . $newValue);
        $this->logger->info('originalValue: ' . $originalValue);
        $entity->setSortorder($newValue);
        $entityCount = count($otherEntities);
        $this->logger->info('entityCount: ' . $entityCount);

        // Moved to a higher position (i.e., lower value)
        if ($newValue < $originalValue) {
            foreach ($otherEntities as $otherEntity) {
                $otherSortOrder = $otherEntity->getSortOrder();
                if ($otherSortOrder >= $newValue && $otherSortOrder < $originalValue) {
                    $otherEntity->setSortOrder($otherSortOrder + 1);
                    $this->logger->info('Incrementing sortOrder for entity with ID: ' . $otherEntity->getId());
                }
            }
        }
        // Moved to a lower position (i.e., higher value)
        elseif ($newValue > $originalValue) {
            foreach ($otherEntities as $otherEntity) {
                $otherSortOrder = $otherEntity->getSortOrder();
                if ($otherSortOrder <= $newValue && $otherSortOrder > $originalValue) {
                    $otherEntity->setSortOrder($otherSortOrder - 1);
                    $this->logger->info('Decrementing sortOrder for entity with ID: ' . $otherEntity->getId());
                }
            }
        }

        // Set the sortOrder for the entity being changed
        $entity->setSortorder($newValue);
    }
    // 
    // 
    // 
    public function updateEntityNameInheritance($entityType, $entity, $newParentName, $originalValue, $field)
    {
        $entityNameParts = explode('.', $entity->getName());
        $entityNameParts = array_reverse($entityNameParts);
        $entityName = $entityNameParts[0];
        $newName = $entityName . '.' . $newParentName;

        $entityId = $entity->getId();
        $this->updateNameAndFolderByParentEntity($entityType, $entityId, $newName, $originalValue, $field);

        $entity->setName($newName);
        $this->em->persist($entity);
        $this->em->flush();
    }
    // 
    // 
    //     
    public function updateDocumentPath($entityType, $entity, $newParentName, $originalValue)
    {
        $public_dir = $this->projectDir . '/public';
        $folderPath = $public_dir . '/doc';

        switch ($entityType) {
            case 'upload':
                $upload = $entity;
                $parts      = explode('.', $newParentName);
                $parts      = array_reverse($parts);
                foreach ($parts as $part) {
                    $folderPath .= '/' . $part;
                }
                $Path = $folderPath . '/' . $upload->getFilename();
                $upload->setPath($Path);
                break;

            case 'oldupload':
                $oldUpload = $entity;
                $parts      = explode('.', $newParentName);
                $parts      = array_reverse($parts);
                foreach ($parts as $part) {
                    $folderPath .= '/' . $part;
                }
                $Path = $folderPath . '/' . $oldUpload->getFilename();
                $oldUpload->setPath($Path);
                break;

            case 'incident':
                $incident = $entity;
                $parts      = explode('.', $newParentName);
                $parts      = array_reverse($parts);
                foreach ($parts as $part) {
                    $folderPath .= '/' . $part;
                }
                $Path = $folderPath . '/' . $incident->getName();
                $incident->setPath($Path);
                break;
        }
    }
    // 
    //     
    //     
    public function updateNameAndFolderByParentEntity($entityType, $id, $newName, $originalValue, $field)
    {
        $this->logger->info('updateNameAndFolderByParentEntity: entityType: ' . $entityType);
        $this->logger->info('updateNameAndFolderByParentEntity: entityId: ' . $id);
        $this->logger->info('updateNameAndFolderByParentEntity: field: ' . $field);
        $this->logger->info('updateNameAndFolderByParentEntity: newValue: ' . $newName);
        $this->logger->info('updateNameAndFolderByParentEntity: originalValue: ' . $originalValue);
        // Get the repository of the entity type
        $repository = null;
        switch ($entityType) {
            case 'zone':
                $repository = $this->zoneRepository;
                break;
            case 'productline':
                $repository = $this->productLineRepository;
                $parentFieldName = 'Zone';
                break;
            case 'category':
                $repository = $this->categoryRepository;
                $parentFieldName = 'ProductLine';
                break;
            case 'button':
                $repository = $this->buttonRepository;
                $parentFieldName = 'Category';
                break;
            case 'upload':
                $repository = $this->uploadRepository;
                $parentFieldName = 'Button';
                break;
            case 'incident':
                $repository = $this->incidentRepository;
                $parentFieldName = 'ProductLine';

                break;
            case 'oldupload':
                $repository = $this->oldUploadRepository;
                $parentFieldName = 'Button';
                break;
        }
        // If the entity type is not valid, return an empty array
        if (!$repository) {
            return [];
        }
        // Get the entity from the database and return an empty array if it doesn't exist
        $entity = $repository->find($id);
        if (!$entity) {
            return [];
        }
        // Depending on the entity type, get the related entities
        if ($entityType === 'zone') {
            foreach ($entity->getProductLines() as $productLine) {
                if ($field === 'name') {
                    $this->updateEntityNameInheritance('productline', $productLine, $newName, $originalValue, $field);
                }
            }
            if ($field === 'sortOrder') {
                $this->updateSortOrders($repository->findAllExceptOne($id), $entity, $newName, $originalValue);
            }
        } elseif ($entityType === 'productline') {
            foreach ($entity->getIncidents() as $incident) {
                if ($field === 'name') {
                    $this->updateDocumentPath('incident', $incident, $newName, $originalValue);
                }
            }
            foreach ($entity->getCategories() as $category) {
                if ($field === 'name') {
                    $this->updateEntityNameInheritance('category', $category, $newName, $originalValue, $field);
                }
            }
            if ($field === 'sortOrder') {

                $parentEntityId = $entity->getZone()->getId();
                $this->updateSortOrders($repository->findAllExceptOneByParent($id, $parentEntityId, $parentFieldName), $entity, $newName, $originalValue);
            }
        } elseif ($entityType === 'category') {
            foreach ($entity->getButtons() as $button) {
                if ($field === 'name') {
                    $this->updateEntityNameInheritance('button', $button, $newName, $originalValue, $field);
                }
            }
            if ($field === 'sortOrder') {

                $parentEntityId = $entity->getProductLine()->getId();
                $this->updateSortOrders($repository->findAllExceptOne($id, $parentEntityId, $parentFieldName), $entity, $newName, $originalValue);
            }
        } elseif ($entityType === 'button') {
            foreach ($entity->getUploads() as $upload) {
                if ($field === 'name') {
                    $this->updateDocumentPath('upload', $upload, $newName, $originalValue);
                }
            }
            foreach ($entity->getOldUploads() as $oldUpload) {
                if ($field === 'name') {
                    $this->updateDocumentPath('oldupload', $oldUpload, $newName, $originalValue);
                }
            }
            if ($field === 'sortOrder') {

                $parentEntityId = $entity->getCategory()->getId();
                $this->updateSortOrders($repository->findAllExceptOneByParent($id, $parentEntityId, $parentFieldName), $entity, $newName, $originalValue);
            }
        }
    }
}