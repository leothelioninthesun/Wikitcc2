<?php
class WikiPagesController extends AppController {
    public $components = array('Search.Prg');
    public $presetVars = true;
    public $helpers = array('Cache');
    public $cacheAction = array(
        'view/' => '1 week'
    );

    public function beforeFilter() {
        parent::beforeFilter();
        $this->Auth->allow('find_public','view');
        if($this->action === 'view'){
            $title = $this->request->params['pass'][0];
            if (!$this->WikiPage->isPublic($title)){
                $this->Auth->deny('view');
            }
        }
    }

    public function index() {
        $posts = $this->paginate();
        if ($this->request->is('requested')) {
            return $posts;
        } else {
            $this->set('posts', $posts);
        }
    }

    public function find(){
        $this->Prg->commonProcess();
        $this->paginate = array(
            'conditions' => $this->WikiPage->parseCriteria($this->passedArgs),
        );
        $posts = $this->paginate();
        if ($this->request->is('requested')) {
            return $posts;
        } else {
            $this->set('posts', $posts);
        }
    }

    public function find_public(){
        $this->Prg->commonProcess();
        $this->passedArgs['is_public'] = 1;
        $this->paginate = array(
            'conditions' => $this->WikiPage->parseCriteria($this->passedArgs),
        );
        $posts = $this->paginate();
        $this->set('posts', $posts);
        if ($this->request->is('requested')) {
            return $posts;
        } else {
            $this->set('posts', $posts);
        }
    }

    public function view($title = null) {
        $this->set('content_title', $title);
        $this->helpers[] = 'WikitccParse';
        if (!$title) {
            throw new NotFoundException('ページが見つかりません');
        }

        $post = $this->WikiPage->findByTitle($title);
        if (!$post) {
            throw new NotFoundException('ページが見つかりません');
        }
        $this->set('parents', $this->WikiPage->Category->getPath($post['Category']['id']));
        //$this->set('content_title', $post['WikiPage']['title']);
        $this->set('data', $post);
    }

    public function add() {
        $categoryList = $this->WikiPage->Category->generateTreeList(null,null,null, '-');
        $this->set('categoryList',$categoryList);
        $this->set('content_title', null);

        if ($this->request->is('post')) {
            $this->WikiPage->create();
            if ($this->WikiPage->save($this->request->data)) {
                $this->Session->setFlash('ページが作成されました');
                return $this->redirect(array('action' => 'view',h($this->request->data['WikiPage']['title'])));
            }
            $this->Session->setFlash('ページを作成できませんでした');
        }
    }

    public function edit($title = null) {
        $categoryList = $this->WikiPage->Category->generateTreeList(null,null,null, '-');
        $this->set('content_title', $title);
        $this->set('categoryList',$categoryList);
        $post = $this->WikiPage->findByTitle($title);
        $this->set('post', $post);

        if ($this->request->is(array('post', 'put'))) {
            if($post){
                $this->WikiPage->id = $post['WikiPage']['id'];
            }
            if ($this->WikiPage->save($this->request->data)) {
                if($post){
                    $this->Session->setFlash('ページが更新されました');
                }else{
                    $this->Session->setFlash('ページが作成されました');
                }
                return $this->redirect(array('action' => 'view',$post['WikiPage']['title']));
            }
            if($post){
                $this->Session->setFlash('ページを更新できませんでした');
            }else{
                $this->Session->setFlash('ページを作成できませんでした');
            }
        }

        //フォームのデフォルト値をここで設定
        if (!$this->request->data) {
            $this->request->data = $post;
        }
    }

    public function delete($title = null) {
        if ($this->request->is('get')) {
            throw new MethodNotAllowedException();
        }

        $post = $this->WikiPage->findByTitle($title);
        if(!$post){
            throw new NotFoundException('ページが見つかりません');
        }
        $id=$post['WikiPage']['id'];

        if ($this->WikiPage->delete($id)) {
            $this->Session->setFlash(__('ページ： %s を削除しました', $title));
            return $this->redirect(array('action' => 'index'));
        }
    }

    public function add_comment(){
        if ($this->request->is('post')) {
            $post = $this->WikiPage->findById($this->request->data['WikiPage']['id']);
            if (!$post) {
                throw new NotFoundException('ページが見つかりません');
            }            
            $this->WikiPage->id = $post['WikiPage']['id'];
            $content = $post['WikiPage']['body'];
            $name = h($this->request->data['WikiPage']['name']);
            $message = h($this->request->data['WikiPage']['comment']);
            if(empty($name)){
                $name = "名無しさん";
            }
            $wiki_comment = "*$message - $name\n";
            $content = str_replace('{{comment}}', $wiki_comment.'{{comment}}', $content);
            $post['WikiPage']['body'] = $content;

            if ($this->WikiPage->save($post)) {
                $this->Session->setFlash(__('ページが更新されました'));
                return $this->redirect(array('action' => 'view',$post['WikiPage']['title']));
            }else{
                $this->Session->setFlash(__('ページを更新できませんでした'));
            }
        }
    }

    public function revisions($title=null){
        $this->WikiPage->Behaviors->load('Revision',array('limit'=>$this->WikiPage->limit_revisions));

        $post = $this->WikiPage->findByTitle($title);
        if(!$post){
            throw new NotFoundException('ページが見つかりません');
        }

        $this->WikiPage->id = $post['WikiPage']['id'];
        //$this->WikiPage->newest();
        $revisions = $this->WikiPage->revisions(array('limit'=>10));
        $this->set('revisions',$revisions);
        $this->set('content_title',$post['WikiPage']['title']);
    }

    public function view_revision($version_id){
        $this->WikiPage->Behaviors->load('Revision',array('limit'=>$this->WikiPage->limit_revisions));

        $post = $this->WikiPage->ShadowModel->findByVersionId($version_id);
        $this->set('post',$post);
    }

    public function view_prev_diff($version_id){
        $this->WikiPage->Behaviors->load('Revision',array('limit'=>$this->WikiPage->limit_revisions));

        $old = $this->WikiPage->ShadowModel->findByVersionId($version_id);
        $this->WikiPage->id = $old['WikiPage']['id'];
        $diff = $this->WikiPage->diff(null,$version_id,array('limit'=>2));
        $this->set('diff',$diff);
    }
    
    public function view_latest_diff($version_id){
        $this->WikiPage->Behaviors->load('Revision',array('limit'=>$this->WikiPage->limit_revisions));

        $old = $this->WikiPage->ShadowModel->findByVersionId($version_id);
        $current = $this->WikiPage->findById($old['WikiPage']['id']);
        $diff = array_merge_recursive($current, $old);
        $this->set('diff',$diff);
        $this->render('view_prev_diff');
    }
}
