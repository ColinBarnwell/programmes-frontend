<?php
declare(strict_types = 1);

namespace App\Controller;

use App\ValueObject\MetaContext;
use BBC\BrandingClient\BrandingClient;
use BBC\BrandingClient\OrbitClient;
use Symfony\Bundle\TwigBundle\Controller\ExceptionController as BaseExceptionController;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Twig_Environment;

class ExceptionController extends BaseExceptionController
{
    /**
     * @var BrandingClient
     */
    private $brandingClient;

    /**
     * @var OrbitClient
     */
    private $orbitClient;

    public function __construct(Twig_Environment $twig, BrandingClient $brandingClient, OrbitClient $orbitClient, $debug)
    {
        parent::__construct($twig, $debug);
        $this->brandingClient = $brandingClient;
        $this->orbitClient = $orbitClient;
    }

    public function __invoke(Request $request, FlattenException $exception, DebugLoggerInterface $logger = null)
    {
        $currentContent = $this->getAndCleanOutputBuffering($request->headers->get('X-Php-Ob-Level', -1));
        $showException = $request->attributes->get('showException', $this->debug); // As opposed to an additional parameter, this maintains BC

        $code = $exception->getStatusCode();
        $orb = $branding = null; //No need for Orb or Branding when developing locally

        if (!$showException) {
            $branding = $this->brandingClient->getContent('br-00001');

            $orb = $this->orbitClient->getContent([
                'variant' => $branding->getOrbitVariant(),
                'language' => 'en_GB',
            ], [
                'searchScope' => $branding->getOrbitSearchScope(),
                'skipLinkTarget' => 'programmes-content',
            ]);
        }

        $headers = [
            'Content-Type' => $request->getMimeType($request->getRequestFormat()) ?: 'text/html',
            // The page can only be displayed in a frame on the same origin as the page itself.
            'X-Frame-Options' => 'SAMEORIGIN',
            // Blocks a request if the requested type is different from the MIME type
            'X-Content-Type-Options' => 'nosniff',
        ];

        // In production, cache 4xx error codes for a little while
        if (!$showException && $code >= 400 && $code <= 499) {
            $headers['Cache-Control'] = 'public, max-age=60';
        }

        // The 200 status here is a misnomer, it is a default and shall be
        // overridden later.
        return new Response($this->twig->render(
            (string) $this->findTemplate($request, $request->getRequestFormat(), $code, $showException),
            [
                'status_code' => $code,
                'status_text' => isset(Response::$statusTexts[$code]) ? Response::$statusTexts[$code] : '',
                'exception' => $exception,
                'logger' => $logger,
                'currentContent' => $currentContent,
                'orb' => $orb,
                'branding' => $branding,
                'meta_context' => new MetaContext(null, ''),
            ]
        ), 200, $headers);
    }
}
