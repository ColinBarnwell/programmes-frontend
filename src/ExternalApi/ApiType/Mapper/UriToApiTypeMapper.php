<?php
declare(strict_types = 1);

namespace App\ExternalApi\ApiType\Mapper;

use App\ExternalApi\ApiType\ApiTypeEnum;
use Psr\Http\Message\UriInterface;

class UriToApiTypeMapper
{
    private $mappings = [];

    public const MAPPING = [
        ApiTypeEnum::API_ADA => '%^ada\.(int\.|test\.|stage\.)?api\.bbci\.co\.uk%i',
        ApiTypeEnum::API_BRANDING => '%^branding\.(int\.|test\.|stage\.)?files\.bbci\.co\.uk%i',
        ApiTypeEnum::API_ELECTRON => '%^api\.(int\.|test\.|stage\.|live\.)bbc\.co\.uk/electron%i',
        ApiTypeEnum::API_ISITE => '%^api\.(int\.|test\.|stage\.|live\.)bbc\.co\.uk/isite2-content-reader%i',
        ApiTypeEnum::API_ORBIT => '%^navigation\.(int\.|test\.|stage\.)?api\.bbci\.co\.uk%i',
        ApiTypeEnum::API_RECIPE => '%^api\.(int\.|test\.|stage\.|live\.)bbc\.co\.uk/food/recipes%i',
        ApiTypeEnum::API_RECOMMENDATIONS => '%^open\.live\.bbc\.co\.uk/recommend/items%i',
        ApiTypeEnum::API_MORPH => '%^morph\.(int\.|test\.|stage\.)?api\.bbci\.co\.uk%i',
        ApiTypeEnum::API_SOUNDSNAV => '%^sounds-nav\.(int\.|test\.|stage\.)?api\.bbci\.co\.uk\/json%i',
        ApiTypeEnum::API_TUPAC => '%^music-tupac\.(int\.|test\.|stage\.)?api\.bbc\.co\.uk/music/v2%i',
    ];

    public function getApiNameFromUriInterface(UriInterface $uri) : ?string
    {
        $partialUrl = $uri->getHost() . $uri->getPath();
        if (array_key_exists($partialUrl, $this->mappings)) {
            return $this->mappings[$partialUrl];
        }
        $this->mappings[$partialUrl] = null;
        foreach (self::MAPPING as $apiName => $apiPattern) {
            if (preg_match($apiPattern, $partialUrl)) {
                $this->mappings[$partialUrl] = $apiName;
                return $apiName;
            }
        }
        return null;
    }
}
