{* Карточка статьи в списке. Ожидает переменную post — PostListItem. *}
<article class="post-card">
    {if $post->imageUrl}
        <a class="post-card__image" href="/posts/{$post->slug}">
            <img src="{$post->imageUrl}" alt="" loading="lazy" width="800" height="450">
        </a>
    {/if}

    <div class="post-card__body">
        <h3 class="post-card__title">
            <a href="/posts/{$post->slug}">{$post->title}</a>
        </h3>

        <p class="post-card__description">{$post->description}</p>

        <p class="post-card__meta">
            <time datetime="{$fmt->isoDate($post->publishedAt)}">{$fmt->date($post->publishedAt)}</time>
            <span class="post-card__views">{$fmt->views($post->views)}</span>
        </p>
    </div>
</article>
