<?php

namespace App\Controller;

use Doctrine\ORM\Query\Expr\Base;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

// This controller is responsible for rendering the tutorial interface
class TutorialController extends FrontController
{
    #[Route('/tutorial', name: 'app_tutorial')]
    public function index(): Response
    {
        return $this->render('tutorial/tutorial_index.html.twig');
    }
}