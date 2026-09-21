{* Постраничная навигация. Ожидает pages, previousUrl и nextUrl. *}
{if $pages}
    <nav class="pagination" aria-label="Страницы">
        {if $previousUrl}
            <a class="pagination__step" href="{$previousUrl}" rel="prev">Назад</a>
        {else}
            <span class="pagination__step pagination__step--disabled">Назад</span>
        {/if}

        <ul class="pagination__list">
            {foreach $pages as $page}
                <li class="pagination__item">
                    {if $page === null}
                        <span class="pagination__gap">…</span>
                    {elseif $page.current}
                        <span class="pagination__link pagination__link--current" aria-current="page">{$page.number}</span>
                    {else}
                        <a class="pagination__link" href="{$page.url}">{$page.number}</a>
                    {/if}
                </li>
            {/foreach}
        </ul>

        {if $nextUrl}
            <a class="pagination__step" href="{$nextUrl}" rel="next">Вперёд</a>
        {else}
            <span class="pagination__step pagination__step--disabled">Вперёд</span>
        {/if}
    </nav>
{/if}
