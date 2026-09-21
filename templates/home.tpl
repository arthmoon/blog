{extends file="layout.tpl"}

{block name="title"}Блог{/block}

{block name="content"}
    {if !$sections}
        <p class="empty">Пока нет ни одной опубликованной статьи.</p>
    {else}
        {foreach $sections as $section}
            <section class="category-section">
                <header class="category-section__header">
                    <h2 class="category-section__title">
                        <a href="/category/{$section->category->slug}">{$section->category->title}</a>
                    </h2>

                    <a class="button" href="/category/{$section->category->slug}">Все статьи</a>
                </header>

                <div class="post-grid">
                    {foreach $section->posts as $post}
                        {include file="partials/post-card.tpl" post=$post}
                    {/foreach}
                </div>
            </section>
        {/foreach}
    {/if}
{/block}
