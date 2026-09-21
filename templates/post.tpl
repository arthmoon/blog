{extends file="layout.tpl"}

{block name="title"}{$post->title} — Блог{/block}

{block name="content"}
    <article class="post">
        <header class="post__header">
            <h1 class="post__title">{$post->title}</h1>

            <p class="post__meta">
                <time datetime="{$fmt->isoDate($post->publishedAt)}">{$fmt->date($post->publishedAt)}</time>
                <span class="post__views">{$fmt->views($post->views)}</span>
            </p>

            {if $post->categories}
                <p class="post__categories">
                    {foreach $post->categories as $category}
                        <a class="tag" href="/category/{$category->slug}">{$category->title}</a>
                    {/foreach}
                </p>
            {/if}
        </header>

        {if $post->imageUrl}
            <img class="post__image" src="{$post->imageUrl}" alt="" width="800" height="450">
        {/if}

        <p class="post__lead">{$post->description}</p>

        <div class="post__body">
            {foreach $fmt->paragraphs($post->body) as $paragraph}
                <p>{$paragraph}</p>
            {/foreach}
        </div>
    </article>

    {if $similar}
        <section class="similar">
            <h2 class="similar__title">Похожие статьи</h2>

            <div class="post-grid">
                {foreach $similar as $post}
                    {include file="partials/post-card.tpl" post=$post}
                {/foreach}
            </div>
        </section>
    {/if}
{/block}
