<?php
// A theme that calls Parsi Date's functions.
echo parsidate('Y/m/d', get_the_date('U'));
echo \per_number(get_comments_number());

if (function_exists('gregdate')) {
    echo gregdate('Y-m-d', '1403-05-12');
}

$object->parsidate('x');
MyClass::per_number(1);
// parsidate('in a comment');
$text = 'parsidate(in a string)';
