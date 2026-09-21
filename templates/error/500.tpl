{extends file="layout.tpl"}

{block name="title"}Ошибка{/block}

{block name="content"}
    <section class="page-error">
        <h1>Что-то пошло не так</h1>
        <p>Мы уже знаем о проблеме и разбираемся.</p>
        <p><a href="/">Вернуться на главную</a></p>

        {* Подробности только в разработке: в сообщении исключения
           вполне может оказаться строка подключения к базе. *}
        {if $debug && $exception}
            <h2>{$exception.class}</h2>
            <p>{$exception.message}</p>
            <pre>{$exception.trace}</pre>
        {/if}
    </section>
{/block}
