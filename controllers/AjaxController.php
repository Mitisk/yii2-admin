<?php
/**
 * AJAX endpoints for the admin panel.
 *
 * @category Controller
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @version  GIT: $Id$
 * @link     https://github.com/mitisk/yii2-admin
 *
 * @php 8.0
 */

namespace Mitisk\Yii2Admin\controllers;


use Mitisk\Yii2Admin\components\FileUploader;
use Mitisk\Yii2Admin\models\AdminUser;
use Yii;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\Response;

/**
 * Handles file uploads and user-search AJAX requests.
 *
 * @category Controller
 * @package  Mitisk\Yii2Admin
 * @author   Mitisk <admin@example.com>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/mitisk/yii2-admin
 */
class AjaxController extends Controller
{
    /**
     * Base RBAC role names that carry no meaningful info and must be hidden.
     */
    private const BASE_ROLES = ['guest', 'user'];

    /**
     * Загрузка аватарок в админке.
     *
     * @return string
     */
    public function actionUploadAvatar()
    {
        $dir = Yii::getAlias('@webroot') . '/users/avatar/';

        if (!is_dir($dir)) {
            FileHelper::createDirectory($dir, 0775, true);
        }

        $userId = Yii::$app->request->get('id');

        // @var AdminUser $user
        $user = $userId ? AdminUser::findOne($userId) : null;

        if ($userId && !$user) {
            return 'Ошибка!';
        }

        $configuration = [
            'limit'       => 1,
            'fileMaxSize' => 10,
            'extensions'  => ['image/*'],
            'title'       => 'auto',
            'uploadDir'   => $dir,
            'replace'     => false,
            'editor'      => [
                'maxWidth'  => 512,
                'maxHeight' => 512,
                'crop'      => false,
                'quality'   => 95,
            ],
            'storageType' => 'local',
        ];

        if (isset($_POST['fileuploader'], $_POST['name'])) {
            $name = str_replace(['/', '\\'], '', $_POST['name']);

            if (is_file($configuration['uploadDir'] . $name)) {
                $configuration['title']   = $name;
                $configuration['replace'] = true;
            }
        }

        $FileUploader = new FileUploader('files', $configuration);
        $data         = $FileUploader->upload();

        if (!empty($data['files'])) {
            if ($user && $user->image) {
                $user->deleteImage();
            }

            if ($user) {
                $user->updateAttributes(
                    ['image' => '/web/users/avatar/' . $data['files'][0]['name']]
                );
            }

            $item           = $data['files'][0];
            $data['files'][0] = [
                'title' => $item['title'],
                'name'  => $item['name'],
                'size'  => $item['size'],
                'size2' => $item['size2'],
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Поиск пользователей для UserField autocomplete.
     * GET ?q=строка → JSON [{id, name, login, email, avatar, roles}]
     *
     * @return array
     */
    public function actionUserSearch(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $q = trim((string)Yii::$app->request->get('q', ''));
        if ($q === '') {
            return [];
        }

        $like  = '%' . $q . '%';
        $users = AdminUser::find()
            ->andWhere(
                ['OR',
                    ['like', 'id',       $q,    false],
                    ['like', 'username', $like, false],
                    ['like', 'name',     $like, false],
                    ['like', 'email',    $like, false],
                ]
            )
            ->limit(10)
            ->all();

        $auth   = Yii::$app->authManager;
        $result = [];

        foreach ($users as $user) {
            $all      = $auth ? array_keys($auth->getRolesByUser($user->id)) : [];
            $roles    = array_values(array_diff($all, self::BASE_ROLES));
            $result[] = [
                'id'     => $user->id,
                'name'   => $user->name ?: $user->username,
                'login'  => $user->username,
                'email'  => $user->email,
                'avatar' => $user->getAvatar(),
                'roles'  => $roles,
            ];
        }

        return $result;
    }

    /**
     * Удаление аватарки пользователя.
     *
     * @return void
     */
    public function actionDeleteAvatar()
    {
        if (isset($_POST['file'])) {
            $userId = Yii::$app->request->get('id');

            // @var AdminUser $user
            $user = AdminUser::findOne($userId);

            if (!$user) {
                return;
            }

            if ($user->image) {
                $user->deleteImage();
            }
        }
    }
}
