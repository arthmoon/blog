{* Базовый макет. Страницы наследуются от него через {extends} и
   переопределяют блоки title и content. *}
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{block name="title"}Блог{/block}</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    {block name="head"}{/block}
</head>
<body>
<header class="site-header">
    <div class="container">
        <a class="site-title" href="/">Блог</a>
    </div>
</header>

<main class="container">
    {block name="content"}{/block}
</main>

<footer class="site-footer">
    <div class="container">
        <p>Тестовое задание: чистый PHP, Smarty и MySQL.</p>
    </div>
</footer>
</body>
</html>
