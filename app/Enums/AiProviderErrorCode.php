<?php

namespace App\Enums;

enum AiProviderErrorCode: string
{
    case Unavailable = 'provider_unavailable';
    case Timeout = 'provider_timeout';
    case Protocol = 'provider_protocol_error';
    case Unauthorized = 'provider_unauthorized';
    case LoginRequired = 'provider_login_required';
    case RateLimited = 'provider_rate_limited';
    case InvalidRequest = 'provider_invalid_request';
    case Failed = 'provider_failed';
    case ToolFailed = 'provider_tool_failed';
}
