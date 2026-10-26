<?php
namespace Controllers;

use Core\View;

class HomeController 
{
    
    public function index() {
        View::render('home', [
            'title' => 'Accueil - Démo',
            'user' => 'Romain'
        ]);
    }
}