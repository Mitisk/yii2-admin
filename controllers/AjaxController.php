<?php

namespace Mitisk\Yii2Admin\controllers;

use Mitisk\Yii2Admin\components\FileUploader;
use Mitisk\Yii2Admin\models\AdminUser;
use Yii;
use yii\helpers\FileHelper;
use yii\web\Controller;

class AjaxController extends Controller
{
    /**
     * Загрузка аватарок в админке
     * @return string
     */
    public function actionUploadAvatar()
    {
        $dir = Yii::getAlias('@webroot') . '/users/avatar/';

        if (!is_dir($dir)) {
            // Создаем директорию со всеми родительскими папками, если их нет
            FileHelper::createDirectory($dir, 0775, true);
        }

        $userId = Yii::$app->request->get('id');

        /** @var AdminUser $user */
        $user = AdminUser::findOne($userId);

        if (!$user) {
            return 'Ошибка!';
        }

        $configuration = [
            'limit' => 1,
            'fileMaxSize' => 10,
            'extensions' => ['image/*'],
            'title' => 'auto',
            'uploadDir' => $dir,
            'replace' => false,
            'editor' => [
                'maxWidth' => 512,
                'maxHeight' => 512,
                'crop' => false,
                'quality' => 95
            ]
        ];

        if (isset($_POST['fileuploader']) && isset($_POST['name'])) {
            $name = str_replace(array('/', '\\'), '', $_POST['name']);
            $editing = isset($_POST['editing']) && $_POST['editing'] == true;

            if (is_file($configuration['uploadDir'] . $name)) {
                $configuration['title'] = $name;
                $configuration['replace'] = true;
            }
        }

        // initialize FileUploader
        $FileUploader = new FileUploader('files', $configuration);

        // call to upload the files
        $data = $FileUploader->upload();

        // change file's public data
        if (!empty($data['files'])) {

            if ($user->image) {
                $user->deleteImage();
            }

            $user->updateAttributes(['image' => '/web/users/avatar/' . $data['files'][0]['name']]);

            $item = $data['files'][0];

            $data['files'][0] = array(
                'title' => $item['title'],
                'name' => $item['name'],
                'size' => $item['size'],
                'size2' => $item['size2']
            );
        }

        // export to js
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function actionDeleteAvatar()
    {
        if (isset($_POST['file'])) {
            $userId = Yii::$app->request->get('id');

            /** @var AdminUser $user */
            $user = AdminUser::findOne($userId);

            if (!$user) {
                return 'Ошибка!';
            }

            if ($user->image) {
                $user->deleteImage();
            }
        }
    }
}
