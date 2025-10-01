<?php
namespace Mitisk\Yii2Admin\components;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

/**
 * Внешний контроллер админки
 * Нужно унаследовать от него для работы с админкой
 */
abstract class ExtAdminController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'index' => ['GET'],
                    'view' => ['GET'],
                    'create' => ['GET','POST'],
                    'update' => ['GET','POST','PUT','PATCH'],
                    'delete' => ['POST','DELETE'],
                ],
            ],
        ];
    }

    public function getViewPath(): string
    {
        // Для контроллеров из пространства имён приложения — старый путь
        if (strpos(static::class, 'app\\controllers\\') === 0) {
            return Yii::getAlias('@app/views/' . $this->id);
        }
        // Для контроллеров внутри модуля — стандартное поведение модуля
        return parent::getViewPath();
    }
}