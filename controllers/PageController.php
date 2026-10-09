<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\components\pages\PagePath;
use Mitisk\Yii2Admin\components\pages\PageTree;
use Mitisk\Yii2Admin\enums\PageStatus;
use Mitisk\Yii2Admin\models\forms\PageForm;
use Mitisk\Yii2Admin\models\Page;
use Mitisk\Yii2Admin\models\PageSearch;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Раздел «Контент» → «Страницы».
 */
class PageController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['actions' => ['index'], 'allow' => true, 'roles' => ['viewContent']],
                    ['actions' => ['update', 'publish', 'unpublish', 'preview-link'], 'allow' => true, 'roles' => ['editContent']],
                    ['actions' => ['create', 'delete', 'duplicate'], 'allow' => true, 'roles' => ['manageContent']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST'], 'publish' => ['POST'], 'unpublish' => ['POST'], 'duplicate' => ['POST'], 'preview-link' => ['POST']],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $search = new PageSearch();
        return $this->render('index', [
            'searchModel' => $search,
            'rows' => $search->search(Yii::$app->request->queryParams),
            'canManage' => Yii::$app->user->can('manageContent'),
            'canEdit' => Yii::$app->user->can('editContent'),
        ]);
    }

    /**
     * @param string|null $path   Адрес со страницы 404: заполняет родителя и слаг.
     * @param int|null    $parent Родитель для «Создать вложенную».
     */
    public function actionCreate(?string $path = null, ?int $parent = null): string|Response
    {
        $page = new Page(['status' => PageStatus::Draft->value, 'template' => 'default', 'parent_id' => $parent]);
        $form = PageForm::fromPage($page, true);
        if ($path !== null && $path !== '') {
            $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $s): bool => $s !== ''));
            $slug = (string)array_pop($segments);
            $parentPage = $segments === [] ? null : Page::find()->byPath(implode('/', $segments))->one();
            $form->parent_id = $parentPage?->id;
            $form->slug = PagePath::isValidSlug($slug) ? $slug : PagePath::slugify($slug);
            $form->slug_auto = 0;
            $form->title = ucfirst(str_replace('-', ' ', $slug));
        }
        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            Yii::$app->session->setFlash('success', 'Страница создана');
            return $this->redirect(['update', 'id' => $page->id]);
        }
        return $this->render('create', ['form' => $form]);
    }

    public function actionUpdate(int $id, int $modal = 0): string|Response
    {
        $page = $this->findPage($id);
        $form = PageForm::fromPage($page, Yii::$app->user->can('manageContent'));
        if ($modal) {
            $this->layout = 'modal';
        }
        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            if ($modal) {
                return $this->render('saved', ['id' => $page->id]);
            }
            Yii::$app->session->setFlash('success', 'Страница сохранена');
            return $this->redirect(['update', 'id' => $page->id]);
        }
        return $this->render('update', ['form' => $form, 'modal' => (bool)$modal]);
    }

    public function actionDelete(int $id): Response
    {
        $page = $this->findPage($id);
        $count = count(PageTree::descendantIds(Page::find()->light()->all(), $id));
        $transaction = Yii::$app->db->beginTransaction();
        try {
            AuditService::log('delete', $page);
            $page->delete();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error('Page delete: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('error', 'Не удалось удалить страницу.');
            return $this->redirect(['index']);
        }
        Yii::$app->pages->invalidate();
        Yii::$app->session->setFlash('success', $count > 0 ? "Страница и вложенные ($count) удалены" : 'Страница удалена');
        return $this->redirect(['index']);
    }

    public function actionPublish(int $id): Response
    {
        return $this->setStatus($this->findPage($id), PageStatus::Published);
    }

    public function actionUnpublish(int $id): Response
    {
        return $this->setStatus($this->findPage($id), PageStatus::Draft);
    }

    public function actionDuplicate(int $id): Response
    {
        $source = $this->findPage($id);
        $copy = new Page($source->getAttributes(null, ['id', 'path', 'og_image_id', 'created_at', 'updated_at', 'created_by', 'updated_by']));
        $copy->status = PageStatus::Draft->value;
        $copy->published_at = null;
        $copy->title = $source->title . ' (копия)';
        // Слаг не длиннее 128: место под «-copy-NNNN»
        $base = rtrim(substr((string)$source->slug, 0, 128 - strlen('-copy-9999')), '-') . '-copy';
        $copy->slug = $base;
        for ($i = 2; Page::find()->children($copy->parent_id === null ? null : (int)$copy->parent_id)->andWhere(['slug' => $copy->slug])->exists(); $i++) {
            $copy->slug = $base . '-' . $i;
        }
        $copy->save(false);
        Yii::$app->pages->invalidate();
        AuditService::log('create', $copy);
        Yii::$app->session->setFlash('success', 'Копия создана черновиком');
        return $this->redirect(['update', 'id' => $copy->id]);
    }

    /** Ссылка предпросмотра для заказчика (JSON, для кнопки в форме и Admin Bar). */
    public function actionPreviewLink(int $id): Response
    {
        $page = $this->findPage($id);
        return $this->asJson(['ok' => true, 'url' => Url::to(Yii::$app->pages->previewUrl($page), true)]);
    }

    private function setStatus(Page $page, PageStatus $status): Response
    {
        $old = $page->status;
        $page->status = $status->value;
        if ($status === PageStatus::Published && ($page->published_at === null || (int)$page->published_at > time())) {
            $page->published_at = time();
        }
        $page->save(false, ['status', 'published_at', 'updated_at', 'updated_by']);
        Yii::$app->pages->invalidate();
        AuditService::log('update', $page, ['status' => $old]);
        $message = $status === PageStatus::Published ? 'Страница опубликована' : 'Страница снята с публикации';
        if (Yii::$app->request->isAjax) {
            return $this->asJson(['ok' => true, 'message' => $message]);
        }
        Yii::$app->session->setFlash('success', $message);
        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }

    /** @throws NotFoundHttpException */
    private function findPage(int $id): Page
    {
        return Page::findOne($id) ?? throw new NotFoundHttpException('Страница не найдена.');
    }
}
