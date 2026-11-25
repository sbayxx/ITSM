<?php
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../models/KnowledgeArticle.php';

class KnowledgeBaseController
{
    private const EDITABLE_FIELDS = ['title','body','category','tags','status','is_internal','reviewer_id'];

    public function index(Request $request): void
    {
        $filters = [
            'status' => $request->query('status', 'published'),
            'category' => $request->query('category'),
            'search' => $request->query('search'),
        ];
        $result = KnowledgeArticle::all(array_filter($filters));
        Response::paginated($result['items'], $result['total'], $result['page'], $result['per_page']);
    }

    public function show(Request $request): void
    {
        $article = KnowledgeArticle::find((int)$request->param('id'));
        if (!$article) {
            Response::error('Article not found', 404);
        }
        Response::success($article);
    }

    public function store(Request $request): void
    {
        $data = array_intersect_key($request->all(), array_flip(self::EDITABLE_FIELDS));
        $v = new Validator($data);
        $v->required('title');
        $v->validate();
        $data['author_id'] = Auth::id();
        $id = KnowledgeArticle::create($data);
        Logger::log('create', 'knowledge_articles', $id, 'Article created: ' . $data['title']);
        Response::success(KnowledgeArticle::find($id), 'Article created', 201);
    }

    public function update(Request $request): void
    {
        $id = (int)$request->param('id');
        $article = KnowledgeArticle::find($id);
        if (!$article) {
            Response::error('Article not found', 404);
        }
        $update = array_intersect_key($request->all(), array_flip(self::EDITABLE_FIELDS));
        if (isset($update['status']) && $update['status'] === 'published' && $article['status'] !== 'published') {
            $update['published_at'] = date('Y-m-d H:i:s');
        }
        if (!empty($update)) {
            KnowledgeArticle::update($id, $update);
            Logger::log('update', 'knowledge_articles', $id, 'Article updated');
        }
        Response::success(KnowledgeArticle::find($id), 'Article updated');
    }

    public function destroy(Request $request): void
    {
        $id = (int)$request->param('id');
        KnowledgeArticle::delete($id);
        Logger::log('delete', 'knowledge_articles', $id, 'Article deleted');
        Response::success(null, 'Article deleted');
    }

    public function helpful(Request $request): void
    {
        $id = (int)$request->param('id');
        $helpful = (bool)$request->input('helpful', true);
        KnowledgeArticle::markHelpful($id, $helpful);
        Response::success(null, 'Feedback recorded');
    }
}
