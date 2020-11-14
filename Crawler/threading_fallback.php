<?php
/*
 * this is just so that in case pthreads isn't available, the classes that inherit Thread don't break
 */

namespace {


    if(!class_exists('Thread')) {
        class Thread
        {
        }
    }
}