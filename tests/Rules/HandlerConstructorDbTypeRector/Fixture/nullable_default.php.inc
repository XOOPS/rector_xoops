<?php

class ItemHandler extends XoopsPersistableObjectHandler
{
    public function __construct($db)
    {
        parent::__construct($db, 'item', 'Item', 'id', 'title');
    }
}

class CategoryHandler extends XoopsPersistableObjectHandler
{
    public function __construct($db = null)
    {
        parent::__construct($db, 'category', 'Category', 'id', 'title');
    }
}

class TagHandler extends XoopsPersistableObjectHandler
{
    public function __construct($db = null, $table)
    {
        parent::__construct($db, $table, 'Tag', 'id', 'title');
    }
}

class Plain
{
    public function __construct($db = null)
    {
    }
}

?>
-----
<?php

class ItemHandler extends XoopsPersistableObjectHandler
{
    public function __construct(\XoopsDatabase $db)
    {
        parent::__construct($db, 'item', 'Item', 'id', 'title');
    }
}

class CategoryHandler extends XoopsPersistableObjectHandler
{
    public function __construct(?\XoopsDatabase $db = null)
    {
        parent::__construct($db, 'category', 'Category', 'id', 'title');
    }
}

class TagHandler extends XoopsPersistableObjectHandler
{
    public function __construct(?\XoopsDatabase $db, $table)
    {
        parent::__construct($db, $table, 'Tag', 'id', 'title');
    }
}

class Plain
{
    public function __construct($db = null)
    {
    }
}

?>
