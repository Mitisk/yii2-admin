<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\AuditService;
use Mitisk\Yii2Admin\components\BaseController;
use Mitisk\Yii2Admin\enums\BlockType;
use Mitisk\Yii2Admin\models\ContentBlock;
use Mitisk\Yii2Admin\models\ContentBlockSearch;
use Mitisk\Yii2Admin\models\forms\ContentBlockForm;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Раздел «Контент» → «Текстовые блоки».
 */
class ContentBlockController extends BaseController
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['actions' => ['index'], 'allow' => true, 'roles' => ['viewContent']],
                    ['actions' => ['update', 'toggle'], 'allow' => true, 'roles' => ['editContent']],
                    ['actions' => ['create', 'delete'], 'allow' => true, 'roles' => ['manageContent']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST'], 'toggle' => ['POST']],
            ],
        ];
    }

    public function actionIndex(): string
    {
        $searchModel = new ContentBlockSearch();
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $searchModel->search(Yii::$app->request->queryParams),
            'canManage' => Yii::$app->user->can('manageContent'),
        ]);
    }

    public function actionCreate(string $type = 'text'): string|Response
    {
        $block = new ContentBlock(['type' => (BlockType::tryFrom($type) ?? BlockType::Text)->value]);
        $form = ContentBlockForm::fromBlock($block, true);
        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            Yii::$app->session->setFlash('success', 'Блок создан');
            return $this->redirect(['update', 'id' => $block->id]);
        }
        return $this->render('create', ['form' => $form]);
    }

    public function actionUpdate(?int $id = null, ?string $key = null, int $modal = 0): string|Response
    {
        $block = $this->findBlock($id, $key);
        $form = ContentBlockForm::fromBlock($block, Yii::$app->user->can('manageContent'));
        if ($modal) {
            $this->layout = 'modal';
        }
        if ($form->load(Yii::$app->request->post()) && $form->save()) {
            if ($modal) {
                return $this->render('saved', ['key' => $block->key]);
            }
            Yii::$app->session->setFlash('success', 'Блок сохранён');
            return $this->redirect(['index']);
        }
        return $this->render('update', ['form' => $form, 'modal' => (bool)$modal]);
    }

    public function actionDelete(int $id): Response
    {
        $block = $this->findBlock($id, null);
        AuditService::log('delete', $block);
        $block->delete();
        Yii::$app->session->setFlash(
            'success',
            $block->from_code
                ? 'Блок удалён. Если он выводится в шаблоне, он вернётся со значением из кода.'
                : 'Блок удалён.'
        );
        return $this->redirect(['index']);
    }

    public function actionToggle(): Response
    {
        $block = $this->findBlock((int)Yii::$app->request->post('id'), null);
        $old = (int)$block->is_active;
        $block->is_active = $old ? 0 : 1;
        $block->save(false, ['is_active', 'updated_at', 'updated_by']);
        AuditService::log('update', $block, ['is_active' => $old]);
        return $this->asJson(['success' => true, 'is_active' => (bool)$block->is_active]);
    }

    /**
     * @throws NotFoundHttpException
     */
    private function findBlock(?int $id, ?string $key): ContentBlock
    {
        $block = match (true) {
            $id !== null && $id > 0 => ContentBlock::findOne($id),
            $key !== null && $key !== '' => ContentBlock::find()->byKey($key)->one(),
            default => null,
        };
        if ($block === null) {
            throw new NotFoundHttpException('Блок не найден.');
        }
        return $block;
    }
}
