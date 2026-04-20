<?php

namespace App\Controllers;

use App\Models\Product;

class HomeController extends BaseController
{
    private Product $productModel;

    public function __construct()
    {
        parent::__construct();
        $this->productModel = new Product();
    }

    public function index(): void
    {
        $recentProducts = $this->productModel->getRecent(8);
        $categories = $this->productModel->getCategoryCounts();

        $this->render('home/index', [
            'title' => 'Home',
            'recent_products' => $recentProducts,
            'categories' => $categories,
        ]);
    }
}
