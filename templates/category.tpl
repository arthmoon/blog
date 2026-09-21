{extends file="layout.tpl"}

{block name="title"}{$category->title} — Блог{/block}

{block name="content"}
    <header class="category-header">
        <h1 class="category-header__title">{$category->title}</h1>

        {if $category->description}
            <p class="category-header__description">{$category->description}</p>
        {/if}

        <p class="category-header__count">{$fmt->posts($posts->total)}</p>
    </header>

    {if $posts->isEmpty()}
        <p class="empty">В этой категории пока нет опубликованных статей.</p>
    {else}
        <nav class="sort" aria-label="Сортировка">
            {foreach $sortOptions as $option}
                {if $option.active}
                    <span class="sort__option sort__option--active" aria-current="true">{$option.label}</span>
                {else}
                    <a class="sort__option" href="{$option.url}">{$option.label}</a>
                {/if}
            {/foreach}
        </nav>

        <div class="post-grid">
            {foreach $posts->items as $post}
                {include file="partials/post-card.tpl" post=$post}
            {/foreach}
        </div>

        {include file="partials/pagination.tpl"}
    {/if}
{/block}
