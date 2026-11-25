<?php
declare(strict_types=1);
namespace Zodream\Http;


enum HttpMethod {
    case Get;
    case Post;
    case Delete;
    case Put;
    case Head;
    case Patch;
    case Search;
    case Options;
}