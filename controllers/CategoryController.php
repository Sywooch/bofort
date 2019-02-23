<?php
/**
 * Created by PhpStorm.
 * User: dasha
 * Date: 29.12.18
 * Time: 17:30
 */

namespace app\controllers;


use app\models\BoatsModel;
use app\models\CategoryForm;
use app\models\CategoryModel;
use Yii;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\UploadedFile;

class CategoryController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['update', 'create', 'index', 'show'],
                        'allow' => true,
                        'roles' => ['admin'],
                    ],
                    [
                        'actions' => ['index', 'show', 'slug'],
                        'allow' => true,
                    ],
                ],
            ],
        ];

    }

    public function actionIndex() {
        $categories = CategoryModel::find()->all();

        return $this->render('index', compact('categories'));
    }

    public function actionShow(int $id) {
        $boats = BoatsModel::find()->where(['category_id' => $id])->all();
        return $this->render('show', compact('boats'));
    }

    public function actionSlug($slug) {
        $category = CategoryModel::find()->where(['slug'=>$slug])->one();

        return $this->render('show', compact('boat', 'model'));
    }

    public function actionUpdate(int $id) {

        $category = CategoryModel::findOne($id);
        if(!$category) throw new ErrorException('Not found.');

        $model = new CategoryForm();

        $post = Yii::$app->request->post();

        if ($model->load($post) && $model->validate()) {
            try {
                $model->images = UploadedFile::getInstances($model, 'images');
                if (!$model->upload()) throw new Exception('Ошибка сохранения изображения!');
            } catch (Exception $e) {
                Yii::error($e->getMessage(), 'app.cayegory.create');
            }

            $id = $model->save($category);

            $this->redirect(['index']);
        }

        $model->loadData($category);
        return $this->render('update', compact('model'));
    }


    public function actionCreate() {
        $model = new CategoryForm();

        $post = Yii::$app->request->post();
        if ($model->load($post) && $model->validate()) {

            try {
                $model->images = UploadedFile::getInstances($model, 'images');
                if (!$model->upload()) throw new Exception('Ошибка сохранения изображения!');
            } catch (Exception $e) {
                Yii::error($e->getMessage(), 'app.category.create');
            }

            $category = new CategoryModel();
            $id = $model->save($category);

            $this->redirect(['show', 'id' => $id]);
        }

        return $this->render('create', compact('model'));
    }
}