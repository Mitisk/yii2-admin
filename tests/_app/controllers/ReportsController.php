<?php

declare(strict_types=1);

namespace Mitisk\Yii2Admin\tests\_app\controllers;

use Mitisk\Yii2Admin\components\ExtAdminController;
use yii\web\Response;

/**
 * Свой экран админки для тестов ExtAdminController.
 */
final class ReportsController extends ExtAdminController
{
    public function actionIndex(): string
    {
        return 'reports';
    }

    public function actionBack(): Response
    {
        return $this->redirectBack('/admin/reports/');
    }
}
