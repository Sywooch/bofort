<?php
/**
 * Created by PhpStorm.
 * User: dasha
 * Date: 29.12.18
 * Time: 18:10
 */

namespace app\controllers;

use app\models\BoatsModel;
use app\models\OrderConfirmForm;
use app\models\OrderCreateForm;
use app\models\OrderSession;
use app\models\OrdersModel;
use app\models\PayForm;
use app\models\PromoModel;
use app\models\ServicesModel;
use DateInterval;
use DatePeriod;
use DateTime;
use Yii;
use yii\base\Exception;
use yii\filters\AccessControl;
use yii\web\Controller;

class OrderController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['create', 'confirm-step1', 'confirm-step2', 'apply-promo', 'add-service',
                                      'remove-service', 'info', 'get-times', 'final', 'refund', 'price'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    public function actionCreate() {

        $post = Yii::$app->request->post();
        $form = new OrderCreateForm();

        $form->load($post);
        $form->user_id = Yii::$app->user->getId();

        if($form->validate()) {
            $order = $form->save();
            return $this->redirect(['/order/confirm-step1', 'id' => $order->id]);
        }

        Yii::$app->session->setFlash("Order-create-error", 'Не удалось создать заказ');
        return $this->redirect(Yii::$app->request->referrer);
    }

    public function actionConfirmStep1(int $id) {
        $order = OrdersModel::findOne($id);
        $model = new OrderConfirmForm();

        $post = Yii::$app->request->post();
        if ($model->load($post)) {
            $order->price = $model->coast;
            $order->datetime_from = $model->datetime_from;
            $order->datetime_to = $model->datetime_to;
            $order->save();
            return $this->redirect(['/order/confirm-step2', 'id' => $order->id]);
        }

        return $this->render('confirm-step1', compact('order', 'model'));
    }

    public function actionConfirmStep2(int $id) {
        $order = OrdersModel::findOne($id);
        $services = $order->boat->services;
        $model = new PayForm();

        return $this->render('confirm-step2', compact('order', 'services', 'model'));
    }

    public function actionFinal() {
        return $this->render('final');
    }

    /**
     * Отмена заказа и возврат денег
     * @param int $id
     * @return \yii\web\Response
     * @throws \CloudPayments\Exception\RequestException
     */
    public function actionRefund(int $id) {

        $order = OrdersModel::findOne($id);
        try {
            $client = new \CloudPayments\Manager(Yii::$app->params['cloud_id'], Yii::$app->params['cloud_private_key']);
            $client->refundPayment($order->transaction->cloud_transaction_id, $order->transaction->total_price);
        } catch (Exception $e){
            Yii::error($e->getMessage(), 'order.refund');
            return $this->asJson(['result' => false]);
        }

        $order->state = 2;
        $order->save();

        $order->transaction->state = 2;
        $order->transaction->save();

        Yii::info("Order [$id] success refund", 'order.refund');
        return $this->asJson(['result' => true]);
    }

    public function actionApplyPromo(int $order_id, string $word) {

        Yii::info("Apply Promo: $word", 'order.apply-promo');

        try {
            $promocode = PromoModel::find()->where(['word' => trim($word), 'is_active' => 1])->one();
            if (!$promocode) throw new Exception('Промокод не найден!');
        } catch (Exception $e) {
            Yii::$app->session->setFlash("order-error", $e->getMessage());
            Yii::error($e->getMessage(), 'order.apply-promo');
            return false;
        }

        try {
            $order = OrdersModel::findOne($order_id);
            $order->applyPromo($promocode);
        } catch (Exception $e) {
            Yii::$app->session->setFlash("order-error", 'Не удалось применить промокод.');
            Yii::error($e->getMessage(), 'order.apply-promo');
            return false;
        }

        return true;
    }

    public function actionAddService(int $order_id, int $service_id) {
        try {
            $order = OrdersModel::findOne($order_id);
            $service = ServicesModel::findOne($service_id);

            $order->link('services', $service);
        } catch (Exception $e) {
            Yii::error($e->getMessage(), 'order.apply-promo');
            Yii::$app->session->setFlash("order-error", 'Не удалось добавить услугу.');
            return false;
        }

        return $this->renderPartial('_payBlock', compact('order'));   ;
    }

    public function actionRemoveService(int $order_id, int $service_id) {
        try {
            $order = OrdersModel::findOne($order_id);
            $service = ServicesModel::findOne($service_id);

            $order->unlink('services', $service);
        } catch (Exception $e) {
            Yii::error($e->getMessage(), 'order.apply-promo');
            Yii::$app->session->setFlash("order-error", 'Не удалось удалить услугу.');
            return false;
        }

        return $this->renderPartial('_payBlock', compact('order'));
    }

    public function actionInfo($id) {
        $order = OrdersModel::findOne($id);

        return $this->renderPartial('_orderInfo', compact('order'));
    }

    /**
     * Достаем занятое время у каждой лодки
     * @param int $boat_id
     * @param $date
     * @return \yii\web\Response
     * @throws \Exception
     */
    public function actionGetTimes(int $boat_id, $date) {

        $date = new DateTime($date.'-01');
        $busyBoats = OrdersModel::find()
                            ->where(['boat_id' => $boat_id, 'state' => 1])
                            ->andWhere(['>=', 'datetime_from', $date->format('Y-m-d')])
                            ->andWhere(['<=',  'datetime_from', $date->modify('+ 1 month')->format('Y-m-d')])
                            ->all();
        $datetimes = [];
        foreach ($busyBoats as &$busyBoat) {
            $begin = new DateTime($busyBoat->datetime_from);
            $end = new DateTime( $busyBoat->datetime_to);
            $interval = new DateInterval('PT1H');
            $range = new DatePeriod($begin, $interval ,$end);
            foreach ($range as $rng) {
                $datetimes[] = $rng->format('Y-m-d\TH:00:00');
            }
        }

        return $this->asJson($datetimes);
    }

    /**
     * Расчет стоимости заказа
     * @param int $boat_id
     * @param $datetime_from
     * @param $datetime_to
     * @return \yii\web\Response
     */
    public function actionPrice(int $boat_id, $datetime_from, $datetime_to) {

        try {
            $boat = BoatsModel::findOne($boat_id);
            if (!$boat) throw new Exception('Лодка не найдена!');

            $price = 0;
            $tariff = $boat->tariff;
            $datetimes = $this->getDateTimeInterval($datetime_from, $datetime_to);

            if (count($datetimes) >= 24) $price = $tariff->one_day;
            elseif (count($datetimes) >= 4) $price = $tariff->four_hours;
            elseif (in_array($datetimes[0]->format('D'), ['Sat', 'Sun'])) $price = $tariff->holiday;
            else $price = $tariff->weekday;

        } catch (Exception $e) {
            Yii::error("Произошла ошибка при расчете тарифа boat_i[$boat_id]");
        }

        return $this->asJson([
           'success' => true,
           'result' => $price*count($datetimes)
        ]);
    }

    private function getDateTimeInterval($datetime_from, $datetime_to, $raw = 1) {
        $begin = new DateTime($datetime_from);
        $end = new DateTime($datetime_to);
        $interval = new DateInterval('PT1H');
        $range = new DatePeriod($begin, $interval ,$end);
        foreach ($range as $rng) {
            $datetimes[] = ($raw)?$rng:$rng->format('Y-m-d\TH:00:00');
        }

        return $datetimes;
    }
}