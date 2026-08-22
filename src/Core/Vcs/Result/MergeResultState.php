<?php

namespace Ezdeliver\Core\Vcs\Result;

enum MergeResultState: int
{
    case OK = 0;
    case CONFLICT = 1;
    case ERROR = 2;
}
