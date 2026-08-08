<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\AppVersion;
use Illuminate\Http\JsonResponse;

/**
 * @group Instance
 *
 * Retrieve this server's instance information.
 *
 * @unauthenticated
 */
class InstanceController extends Controller
{
    /**
     * Constant marker identifying this server as a Wiwit instance.
     */
    private const APPLICATION = 'wiwit';

    /**
     * Instance information
     *
     * Display the instance name and running version.
     *
     * @responseField application Constant marker. Always "wiwit".
     * @responseField instance_name The operator-configured display name of this instance.
     * @responseField version.display Formatted build label, or `null` on an unstamped build.
     * @responseField version.release_tag Git release tag of the running build, or `null`.
     * @responseField version.ref_name Git ref the running build came from, or `null`.
     * @responseField version.commit_sha Full commit SHA of the running build, or `null`.
     * @responseField version.repository_url Source repository for this build, or `null`.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'application' => self::APPLICATION,
            'instance_name' => config('app.name'),
            'version' => AppVersion::fromConfig()->toArray(),
        ])->header('Cache-Control', 'public, max-age=60');
    }
}
