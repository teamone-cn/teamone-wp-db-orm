<?php

namespace Teamone\TeamoneWpDbOrm\Pagination\Contract;

interface Jsonable
{
    /**
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0);
}
