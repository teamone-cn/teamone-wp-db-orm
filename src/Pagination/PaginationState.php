<?php

namespace Teamone\TeamoneWpDbOrm\Pagination;

class PaginationState
{
    public static function resolveUsing()
    {
        Paginator::currentPathResolver(function (){
            $url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

            return $url;
        });

        Paginator::currentPageResolver(function ($pageName = 'page'){
            if (isset($_GET[$pageName])) {
                $page = $_GET[$pageName];
            } else if (isset($_POST[$pageName])) {
                $page = $_POST[$pageName];
            } else {
                $page = 1;
            }

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }

            return 1;
        });

        Paginator::queryStringResolver(function (){
            return $_SERVER['QUERY_STRING'];
        });

        CursorPaginator::currentCursorResolver(function ($cursorName = 'cursor') {
            if (isset($_GET[$cursorName])) {
                $value = $_GET[$cursorName];
            } else if (isset($_POST[$cursorName])) {
                $value = $_POST[$cursorName];
            } else {
                $value = null;
            }

            return Cursor::fromEncoded($value);
        });
    }
}
