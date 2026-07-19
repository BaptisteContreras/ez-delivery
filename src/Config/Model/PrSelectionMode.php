<?php

namespace Ezdeliver\Config\Model;

enum PrSelectionMode: string
{
    case LinkedIssue = 'linked_issue';
    case MrLabel = 'mr_label';
}
