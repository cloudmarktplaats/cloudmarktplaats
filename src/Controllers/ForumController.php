<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\Forum;

class ForumController extends BaseController
{
    private Forum $forumModel;

    public function __construct()
    {
        parent::__construct();
        $this->forumModel = new Forum();
    }

    public function index(): void
    {
        $categories = $this->forumModel->getCategories();
        $this->render('forum/index', [
            'title' => 'Forum',
            'categories' => $categories,
        ]);
    }

    public function category(string $id): void
    {
        $category = $this->forumModel->findCategoryById((int) $id);
        if (!$category) {
            $this->flash('error', 'Categorie niet gevonden.');
            $this->redirect('/forum');
            return;
        }

        $topics = $this->forumModel->getTopics((int) $id);
        $this->render('forum/category', [
            'title' => $category['name'],
            'category' => $category,
            'topics' => $topics,
        ]);
    }

    public function topic(string $id): void
    {
        $topic = $this->forumModel->findTopicById((int) $id);
        if (!$topic) {
            $this->flash('error', 'Topic niet gevonden.');
            $this->redirect('/forum');
            return;
        }

        $this->forumModel->incrementViews((int) $id);
        $replies = $this->forumModel->getReplies((int) $id);

        $this->render('forum/topic', [
            'title' => $topic['title'],
            'topic' => $topic,
            'replies' => $replies,
        ]);
    }

    public function new_category(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (empty($name)) {
                $this->flash('error', 'Naam is verplicht.');
                $this->render('forum/new_category', ['title' => 'Nieuwe Categorie']);
                return;
            }

            $this->forumModel->createCategory($name, $description);
            $this->flash('success', 'Categorie aangemaakt.');
            $this->redirect('/forum');
            return;
        }

        $this->render('forum/new_category', ['title' => 'Nieuwe Categorie']);
    }

    public function new_topic(string $category_id): void
    {
        $category = $this->forumModel->findCategoryById((int) $category_id);
        if (!$category) {
            $this->flash('error', 'Categorie niet gevonden.');
            $this->redirect('/forum');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title) || empty($content)) {
                $this->flash('error', 'Titel en inhoud zijn verplicht.');
                $this->render('forum/new_topic', [
                    'title' => 'Nieuw Topic',
                    'category' => $category,
                ]);
                return;
            }

            $content = self::purify($content);

            $topicId = $this->forumModel->createTopic(
                (int) $category_id,
                $this->userId(),
                $title,
                $content
            );
            $this->flash('success', 'Topic aangemaakt.');
            $this->redirect('/forum/topic/' . $topicId);
            return;
        }

        $this->render('forum/new_topic', [
            'title' => 'Nieuw Topic',
            'category' => $category,
        ]);
    }

    private static function purify(string $html): string
    {
        $config = \HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,b,i,strong,em,a[href],ul,ol,li,code,pre,blockquote');
        $config->set('AutoFormat.AutoParagraph', true);
        $purifier = new \HTMLPurifier($config);
        return $purifier->purify($html);
    }

    public function reply(string $topic_id): void
    {
        $content = self::purify(trim($_POST['content'] ?? ''));
        if (empty($content)) {
            $this->flash('error', 'Reactie mag niet leeg zijn.');
            $this->redirect('/forum/topic/' . $topic_id);
            return;
        }

        $this->forumModel->createReply((int) $topic_id, $this->userId(), $content);
        $this->flash('success', 'Reactie geplaatst.');
        $this->redirect('/forum/topic/' . $topic_id);
    }
}
