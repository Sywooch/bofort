<?php

/* @var $this yii\web\View */

/** @var \app\models\BoatsModel $boats */

$this->title = 'Bofort';
?>
<div class="site-index">

    <!-- <div class="jumbotron"></div> -->

    <div class="body-content">

        <div class="row"><div class=""><img src="/img/content/hero.jpg" alt="" class="img-responsive"></div></div>

        <div class="row mt-32">
            <div class="col-md-12">
                <p>
                    <strong>Миссия нашей компании</strong> - сделать аренду маломерных судов максимально простой и удобной. Желающим арендовать судно мы предлагаем онлайн бронирование с оплатой по банковской карте. Весь процесс занимает не более 5 минут и это не сложнее, чем вызвать такси.
                </p>
                <p>
                    <strong>Опытным судоводителям, имеющим удостоверение ГИМС</strong> доступны все наши суда для самостоятельного судовождения, а также и долгосрочная аренда для длительных путешествий.
                </p>
                <p>
                    <strong>Имеете удостоверение, но не уверены в своих навыках?</strong> Или не имеете удостоверения вовсе? Для вас у нас есть услуга «наш капитан». Мы не только придадим вам уверенности в судовождении, но и, при желании, любой сможет почувствовать себя капитаном!
                </p>
                <p>
                    Не раздумывайте - выбирайте и бронируйте! С нами вы получите незабываемые впечатления!
                </p>

                <a class="btn btn-primary mt-16" href="/site/about">О компании</a>
                
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="divider mt-64 mb-32"><img class="center-block" src="/img/divider.png" alt=""></div>
            </div>
        </div>

        <div class="row">
            
            <div class="col-md-offset-2 col-md-4">
                <ul>
                    <li>Быстрое онлайн-бронирование</li>
                    <li>Без депозита</li>
                    <li>Только новые катера и яхты</li>
                </ul>
            </div>

            <div class="col-md-4">
                <ul>
                    <li>Быстрое онлайн-бронирование</li>
                    <li>Без депозита</li>
                    <li>Только новые катера и яхты</li>
                </ul>
            </div>

        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="divider mt-32 mb-32"><img class="center-block" src="/img/divider.png" alt=""></div>
            </div>
        </div>

        <div class="row">

            <div class="col-md-12 mb-16">
                <h3 class="text-center">Выбери свое судно</h3>
            </div>

            <?php foreach ($boats as $boat): ?>
                <div class="col-md-3">
                    <div class="boats-image">
                        <img src="http://bofort.su/uploads/250X150/<?= $boat->image->path ?>">
                        <span class="label label-default"><?= $boat->tariff->weekday ?></span>
                    </div>

                    <p><?= $boat->short_description ?></p>
                    <a class="btn btn-primary" href="/boats/show?id=<?= $boat->id ?>">Подробно</a>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (Yii::$app->user->isGuest): ?>
            <div class="row">
                <?= $this->render('/user/default/register', ['user' => $user, 'profile' => $profile]) ?>
            </div>
        <?php endif; ?>

    </div>
</div>
