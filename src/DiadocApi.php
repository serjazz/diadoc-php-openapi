<?php

namespace MagDv\Diadoc;

use Diadoc\Proto\Documents\Types\GetDocumentTypesResponseV2;
use Diadoc\Proto\Events\SignedContent;
use Exception;
use DateTime;
use Diadoc\Proto\AcquireCounteragentRequest;
use Diadoc\Proto\AcquireCounteragentResult;
use Diadoc\Proto\AsyncMethodResult;
use Diadoc\Proto\Box;
use Diadoc\Proto\Counteragent;
use Diadoc\Proto\CounteragentCertificateList;
use Diadoc\Proto\CounteragentList;
use Diadoc\Proto\CounteragentStatus;
use Diadoc\Proto\Department;
use Diadoc\Proto\Docflow\GetDocflowBatchRequest;
use Diadoc\Proto\Docflow\GetDocflowBatchResponse;
use Diadoc\Proto\Docflow\GetDocflowEventsRequest;
use Diadoc\Proto\Docflow\GetDocflowEventsResponse;
use Diadoc\Proto\Docflow\GetDocflowsByPacketIdRequest;
use Diadoc\Proto\Docflow\GetDocflowsByPacketIdResponse;
use Diadoc\Proto\Docflow\SearchDocflowsRequest;
use Diadoc\Proto\Docflow\SearchDocflowsResponse;
use Diadoc\Proto\DocumentId;
use Diadoc\Proto\Documents\Document;
use Diadoc\Proto\Documents\DocumentList;
use Diadoc\Proto\Events\BoxEvent;
use Diadoc\Proto\Events\BoxEventList;
use Diadoc\Proto\Events\Message;
use Diadoc\Proto\Events\MessagePatch;
use Diadoc\Proto\Events\MessagePatchToPost;
use Diadoc\Proto\Events\MessageToPost;
use Diadoc\Proto\Forwarding\ForwardDocumentRequest;
use Diadoc\Proto\GetOrganizationsByInnListRequest;
use Diadoc\Proto\GetOrganizationsByInnListResponse;
use Diadoc\Proto\InvitationDocument;
use Diadoc\Proto\Organization;
use Diadoc\Proto\OrganizationList;
use Diadoc\Proto\OrganizationUserPermissions;
use Diadoc\Proto\OrganizationUsersList;
use Diadoc\Proto\RussianAddress;
use Diadoc\Proto\SortDirection;
use Diadoc\Proto\TimeBasedFilter;
use Diadoc\Proto\Timestamp;
use Diadoc\Proto\User;
use MagDv\Diadoc\Exception\DiadocApiException;
use MagDv\Diadoc\Exception\DiadocApiUnauthorizedException;
use MagDv\Diadoc\Filter\DocumentsFilter;
use MagDv\Diadoc\Helper\DateHelper;
use MagDv\Diadoc\Signer\Interfaces\SignerProviderInterface;

class DiadocApi
{
    /**
     * @var string
     */
    public const METHOD_GET = 'GET';

    /**
     * @var string
     */
    public const CONTENT_FORM_URL_ENCODED = 'application/x-www-form-urlencoded';

    /**
     * @var string
     */
    public const METHOD_POST = 'POST';

    /**
     * Путь OpenID Connect на identity.kontur.ru (см. документацию Диадок API).
     *
     * @var string
     */
    private const IDENTITY_PATH_AUTHORIZE = '/connect/authorize';

    /**
     * @var string
     */
    private const IDENTITY_PATH_TOKEN = '/connect/token';

    /**
     * @var string
     */
    public const RESOURCE_GET_EXTERNAL_SERVICE_AUTH_INFO = '/GetExternalServiceAuthInfo';

    // Organizations
    /**
     * @var string
     */
    public const RESOURCE_GET_BOX = '/GetBox';

    /**
     * @var string
     */
    public const RESOURCE_GET_DEPARTMENT = '/GetDepartment';

    /**
     * @var string
     */
    public const RESOURCE_GET_MY_ORGANIZATION = '/GetMyOrganizations';

    /**
     * @var string
     */
    public const RESOURCE_GET_MY_PERMISSIONS = '/GetMyPermissions';

    /**
     * @var string
     */
    public const RESOURCE_GET_MY_USER = '/GetMyUser';

    /**
     * @var string
     */
    public const RESOURCE_GET_ORGANIZATION = '/GetOrganization';

    /**
     * @var string
     */
    public const RESOURCE_GET_ORGANIZATIONS_BY_INN_KPP = '/GetOrganizationsByInnKpp';

    /**
     * @var string
     */
    public const RESOURCE_GET_ORGANIZATIONS_BY_INN_LIST = '/GetOrganizationsByInnList';

    /**
     * @var string
     */
    public const RESOURCE_GET_ORGANIZATION_USERS = '/GetOrganizationUsers';

    /**
     * @var string
     */
    public const RESOURCE_PARSE_RUSSIAN_ADDRESS = '/ParseRussianAddress';

    // Counteragents
    /**
     * @var string
     */
    public const RESOURCE_ACQUIRE_COUNTERAGENTS = '/AcquireCounteragent';

    /**
     * @var string
     */
    public const RESOURCE_ACQUIRE_COUNTERAGENTS_V2 = '/V2/AcquireCounteragent';

    /**
     * @var string
     */
    public const RESOURCE_ACQUIRE_COUNTERAGENT_RESULT = '/AcquireCounteragentResult';

    /**
     * @var string
     */
    public const RESOURCE_BREAK_WITH_COUNTERAGENT = '/BreakWithCounteragent';

    /**
     * @var string
     */
    public const RESOURCE_GET_COUNTERAGENT = '/GetCounteragent';

    /**
     * @var string
     */
    public const RESOURCE_GET_COUNTERAGENT_V2 = '/V2/GetCounteragent';

    /**
     * @var string
     */
    public const RESOURCE_GET_COUNTERAGENTS = '/GetCounteragents';

    /**
     * @var string
     */
    public const RESOURCE_GET_COUNTERAGENTS_V2 = '/V2/GetCounteragents';

    /**
     * @var string
     */
    public const RESOURCE_GET_COUNTERAGENTS_V3 = '/V3/GetCounteragents';

    /**
     * @var string
     */
    public const RESOURCE_GET_COUNTERAGENT_CERTIFICATES = '/GetCounteragentCertificates';

    // Messages
    /**
     * @var string
     */
    public const RESOURCE_GET_ENTITY_CONTENT = ' /V4/GetEntityContent';

    /**
     * @var string
     */
    public const RESOURCE_GET_MESSAGE = '/V3/GetMessage';

    /**
     * @var string
     */
    public const RESOURCE_POST_MESSAGE = '/V3/PostMessage';

    /**
     * @var string
     */
    public const RESOURCE_POST_MESSAGE_PATCH = '/V3/PostMessagePatch';

    // Documents
    /**
     * @var string
     */
    public const RESOURCE_DELETE = '/Delete';

    /**
     * @var string
     */
    public const RESOURCE_FORWARD_DOCUMENT = '/V2/ForwardDocument';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_ACCEPTANCE_CERTIFICATE_XML_FOR_BUYER = '/GenerateAcceptanceCertificateXmlForBuyer';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_ACCEPTANCE_CERTIFICATE_XML_FOR_SELLER = '/GenerateAcceptanceCertificateXmlForSeller';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_DOCUMENT_PROTOCOL = '/GenerateDocumentProtocol';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_DOCUMENT_ZIP = '/GenerateDocumentZip';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_FORWARDED_DOCUMENT_PROTOCOL = '/V2/GenerateForwardedDocumentProtocol';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_PRINT_FORM = '/GeneratePrintForm';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_PRINT_FORM_FROM_ATTACHMENT = '/GeneratePrintFormFromAttachment';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_REVOCATION_REQUEST_XML = '/GenerateRevocationRequestXml';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_SIGNATURE_REJECTION_XML = '/GenerateSignatureRejectionXml';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_TORG_12_XML_FOR_BUYER = '/GenerateTorg12XmlForBuyer';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_TORG_12_XML_FOR_SELLER = '/GenerateTorg12XmlForSeller';

    /**
     * @var string
     */
    public const RESOURCE_GET_DOCUMENT = '/V3/GetDocument';

    /**
     * @var string
     */
    public const RESOURCE_GET_DOCUMENTS = '/V3/GetDocuments';

    /**
     * @var string
     */
    public const RESOURCE_GET_DOCUMENT_TYPES = '/V2/GetDocumentTypes';

    /**
     * @var string
     */
    public const RESOURCE_GET_FORWARDED_DOCUMENT_EVENTS = '/V2/GetForwardedDocumentEvents';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_FORWARDED_DOCUMENT_PRINT_FORM = '/GenerateForwardedDocumentPrintForm';

    /**
     * @var string
     */
    public const RESOURCE_GET_FORWARDED_ENTITY_CONTENT = '/V2/GetForwardedEntityContent';

    /**
     * @var string
     */
    public const RESOURCE_GET_FORWARDED_DOCUMENT = '/V2/GetForwardedDocuments';

    /**
     * @var string
     */
    public const RESOURCE_GET_GENERATED_PRINT_FORM = '/GetGeneratedPrintForm';

    /**
     * @var string
     */
    public const RESOURCE_GET_RECOGNIZED = '/GetRecognized';

    /**
     * @var string
     */
    public const RESOURCE_MOVE_DOCUMENTS = '/MoveDocuments';

    /**
     * @var string
     */
    public const RESOURCE_PARSE_ACCEPTANCE_CERTIFICATE_SELLER_TITLE_XML = '/ParseAcceptanceCertificateSellerTitleXml';

    /**
     * @var string
     */
    public const RESOURCE_PARSE_REVOCATION_REQUEST_XML = '/ParseRevocationRequestXml';

    /**
     * @var string
     */
    public const RESOURCE_PARSE_SIGNATURE_REJECTION_XML = '/ParseSignatureRejectionXml';

    /**
     * @var string
     */
    public const RESOURCE_PARSE_TORG_12_SELLER_TITLE_XML = '/ParseTorg12SellerTitleXml';

    /**
     * @var string
     */
    public const RESOURCE_PREPARE_DOCUMENTS_TO_SIGN = '/PrepareDocumentsToSign';

    /**
     * @var string
     */
    public const RESOURCE_RECOGNIZE = '/Recognize';

    /**
     * @var string
     */
    public const RESOURCE_RECYCLE_DRAFT = '/RecycleDraft';

    /**
     * @var string
     */
    public const RESOURCE_RESTORE = '/Restore';

    /**
     * @var string
     */
    public const RESOURCE_SHELF_UPLOAD = '/ShelfUpload';

    /**
     * @var string
     */
    public const RESOURCE_SEND_DRAFT = '/SendDraft';

    // SF/ISF/KSF
    /**
     * @var string
     */
    public const RESOURCE_CAN_SEND_INVOICE = '/CanSendInvoice';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_INVOICE_XML = '/GenerateInvoiceXml';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_INVOICE_CORRECTION_REQUEST_XML = '/GenerateInvoiceCorrectionRequestXml';

    /**
     * @var string
     */
    public const RESOURCE_GENERATE_INVOICE_DOCUMENT_RECEIPT_XML = '/GenerateInvoiceDocumentReceiptXml';

    /**
     * @var string
     */
    public const RESOURCE_GET_INVOICE_CORRECTION_REQUEST_INFO = '/GetInvoiceCorrectionRequestInfo';

    /**
     * @var string
     */
    public const RESOURCE_PARSE_INVOICE_XML = '/ParseInvoiceXml';

    // Events
    /**
     * @var string
     */
    public const RESOURCE_GET_EVENT = '/V2/GetEvent';

    /**
     * @var string
     */
    public const RESOURCE_GET_NEW_EVENTS = '/V4/GetNewEvents';

    //Docflow API
    /**
     * @var string
     */
    public const RESOURCE_GET_DOCFLOWS = '/V2/GetDocflows';

    /**
     * @var string
     */
    public const RESOURCE_GET_DOCFLOWS_BY_PACKET_ID = '/V2/GetDocflowsByPacketId';

    /**
     * @var string
     */
    public const RESOURCE_SEARCH_DOCFLOWS = '/V2/SearchDocflows';

    /**
     * @var string
     */
    public const RESOURCE_GET_DOCFLOWS_EVENTS = '/V2/GetDocflowEvents';

    // Cloud sign
    /**
     * @var string
     */
    public const RESOURCE_CLOUD_SIGN = '/CloudSign';

    /**
     * @var string
     */
    public const RESOURCE_CLOUD_SIGN_CONFIRM = '/CloudSignConfirm';

    /**
     * @var string
     */
    public const RESOURCE_CLOUD_SIGN_CONFIRM_RESULT = '/CloudSignConfirmResult';

    /**
     * @var string
     */
    public const RESOURCE_CLOUD_SIGN_RESULT = '/CloudSignResult';

    /**
     * @var string
     */
    public const RESOURCE_AUTO_SIGN_RECEIPTS = '/AutoSignReceipts';

    /**
     * @var string
     */
    public const RESOURCE_AUTO_SIGN_RECEIPTS_RESULT = '/AutoSignReceiptsResult';

    /**
     * @var string|null
     */
    private $token = null;

    /**
     * @var string|null
     */
    private $refreshToken = null;

    /**
     * Unix-время истечения access_token (абсолютное), либо null если неизвестно.
     *
     * @var int|null
     */
    private $accessTokenExpiresAt = null;

    /**
     * @var string
     */
    private $clientId;

    /**
     * @var string
     */
    private $clientSecret;

    /**
     * @var string
     */
    private $serviceUrl;

    /**
     * Базовый URL OpenID (например https://identity.kontur.ru).
     *
     * @var string
     */
    private $identityBaseUrl;

    /**
     * @var string
     */
    private $oidcScope;

    /**
     * @var bool
     */
    private $debugRequest;

    /**
     * @var SignerProviderInterface|null
     */
    private $signerProvider;

    /**
     * @var callable|null function (array $session): void — для сохранения access/refresh после обновления
     */
    private $oauthSessionPersistenceCallback;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $serviceUrl = 'https://diadoc-api.kontur.ru/',
        string $identityBaseUrl = 'https://identity.kontur.ru',
        bool $debugRequest = false,
        SignerProviderInterface $signerProvider = null
    ) {
        // .env с CRLF: в заголовке «\r» ломает HTTP → curl 55 Failed sending HTTP request
        $this->clientId = trim($clientId, " \t\n\r\0\x0B");
        $this->clientSecret = trim($clientSecret, " \t\n\r\0\x0B");
        // Без завершающего «/»: иначе склейка с resource даёт «//»
        $this->serviceUrl = rtrim(trim($serviceUrl, " \t\n\r\0\x0B"), '/');
        $this->identityBaseUrl = rtrim(trim($identityBaseUrl, " \t\n\r\0\x0B"), '/');
        $this->debugRequest = $debugRequest;
        $this->signerProvider = $signerProvider;
        $this->oidcScope = $this->resolveOidcScope($this->serviceUrl);
    }

    /**
     * Колбэк вызывается после обмена кода, refresh и при {@see setOAuthSession}, чтобы сохранить сессию (файл, БД).
     *
     * @param callable|null $callback function (array $session): void
     *        $session = ['access_token' => string, 'refresh_token' => ?string, 'expires_at' => ?int]
     */
    public function setOAuthSessionPersistenceCallback(?callable $callback): void
    {
        $this->oauthSessionPersistenceCallback = $callback;
    }

    /**
     * URL редиректа на страницу авторизации Контур (Authorization Code Flow).
     *
     * @see https://developer.kontur.ru/docs/diadoc-api/authentication.html
     */
    public function buildAuthorizationUrl(string $redirectUri, string $state, ?string $nonce = null): string
    {
        if ($nonce === null || $nonce === '') {
            $nonce = $this->randomUrlSafeString(16);
        }
        $encType = defined('PHP_QUERY_RFC3986') ? PHP_QUERY_RFC3986 : PHP_QUERY_RFC1738;
        $query = http_build_query(
            [
                'response_type' => 'code',
                'client_id' => $this->clientId,
                'scope' => $this->oidcScope,
                'redirect_uri' => $redirectUri,
                'state' => $state,
                'nonce' => $nonce,
            ],
            '',
            '&',
            $encType
        );

        return $this->identityBaseUrl . self::IDENTITY_PATH_AUTHORIZE . '?' . $query;
    }

    /**
     * Обмен authorization code на access/refresh токены.
     *
     * @throws DiadocApiException
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri): void
    {
        $encType = defined('PHP_QUERY_RFC3986') ? PHP_QUERY_RFC3986 : PHP_QUERY_RFC1738;
        $body = http_build_query(
            [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $redirectUri,
            ],
            '',
            '&',
            $encType
        );
        $data = $this->requestIdentityToken($body);
        $this->applyTokenResponse($data);
    }

    /**
     * Обновление access_token по refresh_token.
     *
     * @throws DiadocApiException
     */
    public function refreshAccessToken(): void
    {
        if ($this->refreshToken === null || $this->refreshToken === '') {
            throw new DiadocApiException('refresh_token отсутствует: пройдите Authorization Code Flow заново', 0);
        }
        $encType = defined('PHP_QUERY_RFC3986') ? PHP_QUERY_RFC3986 : PHP_QUERY_RFC1738;
        $body = http_build_query(
            [
                'grant_type' => 'refresh_token',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
            ],
            '',
            '&',
            $encType
        );
        $data = $this->requestIdentityToken($body);
        $this->applyTokenResponse($data);
    }

    /**
     * Установить токены вручную (например из кеша после перезапуска).
     *
     * @param int|null $expiresAtUnix время истечения access_token (unix), null — неизвестно (без проактивного refresh)
     */
    public function setOAuthSession(string $accessToken, ?string $refreshToken = null, ?int $expiresAtUnix = null): void
    {
        $t = $this->normalizeAuthTokenResponse($accessToken);
        $t = $this->sanitizeForHttpHeader($t);
        $this->token = $t !== '' ? $t : null;
        $this->refreshToken = $refreshToken !== null && $refreshToken !== '' ? $this->sanitizeForHttpHeader($refreshToken) : null;
        $this->accessTokenExpiresAt = $expiresAtUnix;
        $this->notifyOAuthSessionPersistence();
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_at: ?int}
     */
    public function getOAuthSessionState(): array
    {
        $access = $this->getToken();

        return [
            'access_token' => $access !== null ? $access : '',
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->accessTokenExpiresAt,
        ];
    }

    public function getOidcScope(): string
    {
        return $this->oidcScope;
    }

    /**
     * Нормализация строки токена (BOM/мусор ломают заголовки).
     *
     * @param string $response
     *
     * @return string
     */
    private function normalizeAuthTokenResponse($response)
    {
        $response = trim((string) $response);
        if (strncmp($response, "\xEF\xBB\xBF", 3) === 0) {
            $response = substr($response, 3);
        }

        return $response;
    }

    /**
     * Убирает управляющие символы из значений в HTTP-заголовках (иначе libcurl может вернуть 55).
     *
     * @param string $value
     *
     * @return string
     */
    private function sanitizeForHttpHeader($value)
    {
        $value = str_replace("\r", '', (string) $value);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }

    /**
     * @param string $serviceUrl
     */
    private function resolveOidcScope($serviceUrl): string
    {
        $base = 'openid profile email offline_access ';
        $host = (string) parse_url($serviceUrl, PHP_URL_HOST);
        $isStaging = stripos($serviceUrl, 'staging') !== false
            || stripos($host, 'diadoc-api-staging') !== false
            || stripos($serviceUrl, 'diadoc-api-test') !== false
            || stripos($host, 'diadoc-api-test') !== false;

        return $base . ($isStaging ? 'Diadoc.PublicAPI.Staging' : 'Diadoc.PublicAPI');
    }

    /**
     * @return string
     */
    private function randomUrlSafeString($byteLength)
    {
        $raw = random_bytes((int) $byteLength);

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestIdentityToken($body)
    {
        $uri = $this->identityBaseUrl . self::IDENTITY_PATH_TOKEN;
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if (defined('CURL_HTTP_VERSION_1_1')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($this->debugRequest) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, STDOUT);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch) !== 0) {
            $err = sprintf('Identity curl error: (%s) %s', curl_errno($ch), curl_error($ch));
            curl_close($ch);
            throw new DiadocApiException($err, curl_errno($ch));
        }
        curl_close($ch);
        if ($response === false) {
            throw new DiadocApiException('Identity token request failed', 0);
        }
        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            throw new DiadocApiException('Identity token response is not JSON: ' . substr((string) $response, 0, 500), (int) $httpCode);
        }
        if ($httpCode !== 200) {
            $msg = isset($data['error_description']) ? (string) $data['error_description'] : (isset($data['error']) ? (string) $data['error'] : 'token error');
            throw new DiadocApiException('Identity /connect/token: ' . $msg, (int) $httpCode);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyTokenResponse(array $data): void
    {
        if (empty($data['access_token']) || !is_string($data['access_token'])) {
            throw new DiadocApiException('Identity token response без access_token', 0);
        }
        $access = $this->normalizeAuthTokenResponse($data['access_token']);
        $access = $this->sanitizeForHttpHeader($access);
        $newRefresh = null;
        if (isset($data['refresh_token']) && is_string($data['refresh_token']) && $data['refresh_token'] !== '') {
            $newRefresh = $this->sanitizeForHttpHeader($data['refresh_token']);
        } elseif ($this->refreshToken !== null) {
            $newRefresh = $this->refreshToken;
        }
        $expiresAt = null;
        if (isset($data['expires_in'])) {
            $expiresIn = (int) $data['expires_in'];
            if ($expiresIn > 0) {
                $expiresAt = time() + $expiresIn;
            }
        }
        $this->token = $access !== '' ? $access : null;
        $this->refreshToken = $newRefresh;
        $this->accessTokenExpiresAt = $expiresAt;
        $this->notifyOAuthSessionPersistence();
    }

    private function notifyOAuthSessionPersistence(): void
    {
        if ($this->oauthSessionPersistenceCallback === null) {
            return;
        }
        call_user_func($this->oauthSessionPersistenceCallback, $this->getOAuthSessionState());
    }

    /**
     * Проактивное обновление access_token по refresh до истечения срока.
     */
    private function ensureAccessTokenFresh($leewaySeconds = 120): void
    {
        if ($this->refreshToken === null || $this->refreshToken === '') {
            return;
        }
        if ($this->accessTokenExpiresAt === null) {
            return;
        }
        if (time() + (int) $leewaySeconds < $this->accessTokenExpiresAt) {
            return;
        }
        $this->refreshAccessToken();
    }

    /**
     * @param string $method self::METHOD_GET|self::METHOD_POST
     */
    private function buildRequestHeaders(?string $contentType = null, string $method = self::METHOD_GET): array
    {
        $token = $this->getToken();
        if ($token === null || $token === '') {
            throw new Exception('Нет access_token: выполните exchangeAuthorizationCode или setOAuthSession/setToken');
        }
        $lines = ['Authorization: Bearer ' . $this->sanitizeForHttpHeader($token)];
        if ($method === self::METHOD_POST) {
            $lines[] = 'Content-Type: ' . ($contentType ?: 'application/x-protobuf');
        } else {
            // GET без тела: не отправляем Content-Type: protobuf — часть nginx отвечает 400 Bad Request
            $lines[] = 'Accept: application/x-protobuf';
            if ($contentType !== null && $contentType !== '') {
                $lines[] = 'Content-Type: ' . $contentType;
            }
        }

        return $lines;
    }

    /**
     * @param array|string $postData Тело POST: массив для form-urlencoded или строка (protobuf и т.п.)
     *
     * @throws DiadocApiException
     * @throws DiadocApiUnauthorizedException
     */
    protected function doRequest(string $resource, $postData = [], array $queryParams = [], string $method = self::METHOD_GET, ?string $contentType = null): string
    {
        $this->ensureAccessTokenFresh();
        if (!$this->getToken()) {
            throw new Exception('Unauthorized request: нет access_token (OIDC)');
        }

        foreach ($queryParams as $k => $v) {
            if ($v === null) {
                unset($queryParams[$k]);
                continue;
            }
            if (is_string($v)) {
                $queryParams[$k] = trim($v, " \t\n\r\0\x0B");
            }
        }
        foreach ($queryParams as $k => $v) {
            if ($v === '') {
                unset($queryParams[$k]);
            }
        }
        $encType = defined('PHP_QUERY_RFC3986') ? PHP_QUERY_RFC3986 : PHP_QUERY_RFC1738;
        $query = http_build_query($queryParams, '', '&', $encType);
        $uri = $this->serviceUrl . $resource . ($query !== '' ? '?' . $query : '');

        $attempt = 0;
        while (true) {
            try {
                return $this->executeDiadocHttpRequest($uri, $postData, $method, $contentType);
            } catch (DiadocApiUnauthorizedException $e) {
                ++$attempt;
                if ($attempt > 1 || $this->refreshToken === null || $this->refreshToken === '') {
                    throw $e;
                }
                $this->refreshAccessToken();
            }
        }
    }

    /**
     * @param array|string $postData
     *
     * @throws DiadocApiException
     * @throws DiadocApiUnauthorizedException
     */
    private function executeDiadocHttpRequest(string $uri, $postData, string $method, ?string $contentType): string
    {
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if (defined('CURL_HTTP_VERSION_1_1')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }
        // 302/301: иначе curl возвращает код редиректа, а не финальный ответ API
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);

        if ($method === self::METHOD_POST) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($postData) ? http_build_query($postData) : $postData);
            if (defined('CURLOPT_POSTREDIR') && defined('CURL_REDIR_POST_ALL')) {
                curl_setopt($ch, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
            }
        } elseif ($method === self::METHOD_GET) {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->buildRequestHeaders($contentType, $method));

        if ($this->debugRequest) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, STDOUT);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch) !== 0) {
            $errno = curl_errno($ch);
            $errstr = curl_error($ch);
            curl_close($ch);
            throw new DiadocApiException(sprintf('Curl error: (%s) %s', $errno, $errstr), $errno);
        }

        if (!($httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE)) || ($httpCode !== 200 && $httpCode !== 204)) {
            $message = sprintf('Curl error http code: (%s) %s', $httpCode, $response);
            curl_close($ch);
            if ($httpCode === 401) {
                throw new DiadocApiUnauthorizedException($message, $httpCode);
            }

            throw new DiadocApiException($message, $httpCode);
        }

        curl_close($ch);

        if ($response === false) {
            throw new DiadocApiException('Diadoc request error false returned');
        }

        return $response;
    }


    /**
     * @throws DiadocApiException
     */
    public function getBox(string $boxId): Box
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_BOX,
            [],
            [
                'boxId' => $boxId
            ]
        );

        $box = new Box();
        $box->mergeFromString($response);

        return $box;
    }

    /**
     *
     * @return Department| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getDepartment(string $orgId, string $departmentId): Department
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_DEPARTMENT,
            [],
            [
                'orgId' => $orgId,
                'departmentId' => $departmentId
            ]
        );

        $department = new Department();
        $department->mergeFromString($response);

        return $department;
    }

    /**
     * @return OrganizationList| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getMyOrganizations(): OrganizationList
    {
        $response = $this->doRequest(self::RESOURCE_GET_MY_ORGANIZATION);

        $organizationList = new OrganizationList();
        $organizationList->mergeFromString($response);

        return $organizationList;
    }

    /**
     *
     * @return OrganizationUserPermissions| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getMyPermissions(string $orgId): OrganizationUserPermissions
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_MY_PERMISSIONS,
            [],
            [
                'orgId' => $orgId
            ]
        );
        $organizationUserPermissions = new OrganizationUserPermissions();
        $organizationUserPermissions->mergeFromString($response);

        return $organizationUserPermissions;
    }

    /**
     * @return User| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getMyUser(): User
    {
        $response = $this->doRequest(self::RESOURCE_GET_MY_USER, [], [], self::METHOD_GET);

        $user = new User();
        $user->mergeFromString($response);

        return $user;
    }

    /**
     * @return Organization| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getOrganizationById(string $orgId): Organization
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_ORGANIZATION,
            [],
            [
                'orgId' => $orgId
            ]
        );

        $organization = new Organization();
        $organization->mergeFromString($response);

        return $organization;
    }

    /**
     * @return Organization| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getOrganizationByFnsParticipantId(string $fnsParticipantId): Organization
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_ORGANIZATION,
            [],
            [
                'fnsParticipantId' => $fnsParticipantId
            ]
        );

        $organization = new Organization();
        $organization->mergeFromString($response);

        return $organization;
    }


    /**
     * @return Organization| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getOrganizationByInn(string $inn): Organization
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_ORGANIZATION,
            [],
            [
                'inn' => $inn
            ]
        );

        $organization = new Organization();
        $organization->mergeFromString($response);

        return $organization;
    }

    /**
     * @param string|null $kpp
     *
     * @return OrganizationList| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getOrganizationsByInnKpp(string $inn, ?string $kpp = null, bool $includeRelations = false): OrganizationList
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_ORGANIZATIONS_BY_INN_KPP,
            [],
            [
                'inn' => $inn,
                'kpp' => $kpp,
                'includeRelations' => $includeRelations ? 'true' : 'false'
            ]
        );

        $organizationList = new OrganizationList();
        $organizationList->mergeFromString($response);

        return $organizationList;
    }

    /**
     * @return GetOrganizationsByInnListResponse| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getOrganizationsByInnList(string $myOrgId, array $innList = []): GetOrganizationsByInnListResponse
    {
        $getOrganizationsByInnListRequest = new GetOrganizationsByInnListRequest();
        $getOrganizationsByInnListRequest->setInnList($innList);

        $response = $this->doRequest(
            self::RESOURCE_GET_ORGANIZATIONS_BY_INN_LIST,
            $getOrganizationsByInnListRequest->serializeToString(),
            [
                'myOrgId'   => $myOrgId
            ],
            self::METHOD_POST
        );

        $getOrganizationsByInnListResponse = new GetOrganizationsByInnListResponse();
        $getOrganizationsByInnListResponse->mergeFromString($response);

        return $getOrganizationsByInnListResponse;
    }

    /**
     *
     * @return OrganizationUsersList| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getOrganizationUsers(string $orgId): OrganizationUsersList
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_ORGANIZATION_USERS,
            [],
            [
                'orgId' => $orgId
            ]
        );

        $organizationUsersList = new OrganizationUsersList();
        $organizationUsersList->mergeFromString($response);

        return $organizationUsersList;
    }

    /**
     *
     * @return RussianAddress| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function parseRussianAddress(string $address): RussianAddress
    {
        $response = $this->doRequest(
            self::RESOURCE_PARSE_RUSSIAN_ADDRESS,
            [],
            [
                'address' => $address
            ]
        );

        $russianAddress = new RussianAddress();
        $russianAddress->mergeFromString($response);

        return $russianAddress;
    }

    /**
     * @param string|null $comment
     *
     * @return mixed
     * @throws DiadocApiException
     */
    public function acquireCounteragent(string $myOrgId, string $counteragentOrgId, string $myDepartmentId, ?string $comment = null): string
    {
        return $this->doRequest(
            self::RESOURCE_ACQUIRE_COUNTERAGENTS,
            [],
            [
                'myOrgId' => $myOrgId,
                'counteragentOrgId' => $counteragentOrgId,
                'myDepartmentId'    => $myDepartmentId,
                'comment'   => $comment
            ],
            self::METHOD_POST
        );
    }

    /**
     * @param InvitationDocument|null $invitationDocument |null $invationDocument
     *
     * @return AsyncMethodResult| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function acquireCounteragentWithDocument(string $myOrgId, string $counteragentOrgId, string $myDepartmentId, ?InvitationDocument $invitationDocument = null, string $messageToContragent = ''): AsyncMethodResult
    {
        $acquireCounteragentRequest = new AcquireCounteragentRequest();
        $acquireCounteragentRequest->setOrgId($counteragentOrgId);
        $acquireCounteragentRequest->setMessageToCounteragent($messageToContragent);
        $acquireCounteragentRequest->setInvitationDocument($invitationDocument);

        $response = $this->doRequest(
            self::RESOURCE_ACQUIRE_COUNTERAGENTS_V2,
            $acquireCounteragentRequest->serializeToString(),
            [
                'myOrgId' => $myOrgId,
                'myDepartmentId'    => $myDepartmentId,
            ],
            self::METHOD_POST
        );

        $asyncMethodResult = new AsyncMethodResult();
        $asyncMethodResult->mergeFromString($response);

        return $asyncMethodResult;
    }

    /**
     * @param InvitationDocument|null $invitationDocument |null $invationDocument
     *
     * @return AsyncMethodResult| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function acquireCounteragentByInnWithDocument(string $myOrgId, string $counteragentInn, ?string $myDepartmentId = null, ?InvitationDocument $invitationDocument = null, string $messageToContragent = ''): AsyncMethodResult
    {
        $acquireCounteragentRequest = new AcquireCounteragentRequest();
        $acquireCounteragentRequest->setInn($counteragentInn);
        $acquireCounteragentRequest->setMessageToCounteragent($messageToContragent);
        $acquireCounteragentRequest->setInvitationDocument($invitationDocument);

        $response = $this->doRequest(
            self::RESOURCE_ACQUIRE_COUNTERAGENTS_V2,
            $acquireCounteragentRequest->serializeToString(),
            [
                'myOrgId' => $myOrgId,
                'myDepartmentId'    => $myDepartmentId,
            ],
            self::METHOD_POST
        );

        $asyncMethodResult = new AsyncMethodResult();
        $asyncMethodResult->mergeFromString($response);

        return $asyncMethodResult;
    }

    /**
     *
     * @return AcquireCounteragentResult| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function acquireCounteragentResult(string $taskId): AcquireCounteragentResult
    {
        $response = $this->doRequest(
            self::RESOURCE_ACQUIRE_COUNTERAGENT_RESULT,
            [],
            [
                'taskId' => $taskId
            ]
        );

        $acquireCounteragentResult = new AcquireCounteragentResult();
        $acquireCounteragentResult->mergeFromString($response);

        return $acquireCounteragentResult;
    }

    /**
     * @throws DiadocApiException
     */
    public function breakWithCounteragent(string $myOrgId, string $counteragentOrgId, string $comment = ''): string
    {
        return $this->doRequest(
            self::RESOURCE_BREAK_WITH_COUNTERAGENT,
            [],
            [
                'myOrgId' => $myOrgId,
                'counteragentOrgId' => $counteragentOrgId,
                'comment' => $comment
            ],
            self::METHOD_POST
        );
    }

    /**
     * @return Counteragent| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getCountragent(string $myOrgId, string $counteragentOrgId): Counteragent
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_COUNTERAGENT,
            [],
            [
                'myOrgId' => $myOrgId,
                'counteragentOrgId' => $counteragentOrgId
            ]
        );
        $counteragent = new Counteragent();
        $counteragent->mergeFromString($response);

        return $counteragent;
    }


    /**
     * @return Counteragent| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getCountragentV2(string $myOrgId, string $counteragentOrgId): Counteragent
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_COUNTERAGENT_V2,
            [],
            [
                'myOrgId' => $myOrgId,
                'counteragentOrgId' => $counteragentOrgId
            ]
        );

        $counteragent = new Counteragent();
        $counteragent->mergeFromString($response);

        return $counteragent;
    }


    /**
     * @param string $myOrgId
     * @param string|null $counteragentStatus
     * @param int|null $afterIndexKey
     * @return CounteragentList
     * @throws DiadocApiException
     * @throws DiadocApiUnauthorizedException
     */
    public function getCountragents(string $myOrgId, ?string $counteragentStatus = null, ?int $afterIndexKey = null): CounteragentList
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_COUNTERAGENTS,
            [],
            [
                'myOrgId'   => $myOrgId,
                'counteragentStatus' => $counteragentStatus,
                'afterIndexKey'  => $afterIndexKey
            ]
        );
        $counteragentList = new CounteragentList();
        $counteragentList->mergeFromString($response);

        return $counteragentList;
    }

    public function getCountragentsV2(string $myOrgId, ?string $counteragentStatus = null, ?string $afterIndexKey = null, ?string $query = null): CounteragentList
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_COUNTERAGENTS_V2,
            [],
            [
                'myOrgId'   => $myOrgId,
                'counteragentStatus' => $counteragentStatus,
                'afterIndexKey'  => $afterIndexKey,
                'query'  => $query,
            ]
        );
        $counteragentList = (new CounteragentList());
        $counteragentList->mergeFromString($response);

        return $counteragentList;
    }

    /**
     * Список контрагентов по ящику (актуальный HTTP-метод Контура). Параметр myBoxId — GUID ящика
     * (как в ответе GetMyOrganizations / поле Box.BoxIdGuid), а не идентификатор организации.
     *
     * @param string|null $counteragentStatus см. CounteragentStatus
     * @param int|null $pageSize от 1 до 100; по умолчанию на стороне API — 100
     *
     * @throws DiadocApiException
     * @throws DiadocApiUnauthorizedException
     */
    public function getCountragentsV3(
        string $myBoxId,
        ?string $counteragentStatus = null,
        ?string $afterIndexKey = null,
        ?string $query = null,
        ?int $pageSize = null
    ): CounteragentList {
        $queryParams = [
            'myBoxId' => $myBoxId,
            'counteragentStatus' => $counteragentStatus,
            'afterIndexKey' => $afterIndexKey,
            'query' => $query,
        ];
        if ($pageSize !== null) {
            $queryParams['pageSize'] = $pageSize;
        }

        $response = $this->doRequest(
            self::RESOURCE_GET_COUNTERAGENTS_V3,
            [],
            $queryParams
        );
        $counteragentList = new CounteragentList();
        $counteragentList->mergeFromString($response);

        return $counteragentList;
    }

    public function getCounteragentCertificates(string $myOrgId, string $counteragentOrgId): CounteragentCertificateList
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_COUNTERAGENT_CERTIFICATES,
            [],
            [
                'myOrgId' => $myOrgId,
                'counteragentOrgId' => $counteragentOrgId
            ]
        );

        $counteragentCertificateList = new CounteragentCertificateList();
        $counteragentCertificateList->mergeFromString($response);

        return $counteragentCertificateList;
    }

    /**
     *
     * @return mixed
     * @throws DiadocApiException
     */
    public function getEntityContent(string $boxId, string $messageId, string $entityId)
    {
        return $this->doRequest(
            self::RESOURCE_GET_ENTITY_CONTENT,
            [],
            [
                'boxId' => $boxId,
                'messageId' => $messageId,
                'entityId'  => $entityId
            ]
        );
    }

    /**
     * @param string|null $entityId
     *
     * @return Message| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getMessage(string $boxId, string $messageId, ?string $entityId = null, ?string $originalSignature = null): Message
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_MESSAGE,
            [],
            [
                'boxId' => $boxId,
                'messageId' => $messageId,
                'entityId'  => $entityId,
                'originalSignature' => $originalSignature
            ]
        );
        $message = new Message();
        $message->mergeFromString($response);

        return $message;
    }

    /**
     * @return Message| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     * @throws DiadocApiUnauthorizedException
     */
    public function postMessage(MessageToPost $messageToPost, ?string $operationId = null): Message
    {
        $response = $this->doRequest(
            self::RESOURCE_POST_MESSAGE,
            $messageToPost->serializeToString(),
            [
                'operationId' => $operationId
            ],
            self::METHOD_POST
        );

        $message = new Message();
        $message->mergeFromString($response);

        return $message;
    }

    /**
     * @return MessagePatch| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function postMessagePatch(MessagePatchToPost $messagePatchToPost, ?string $operationId = null): MessagePatch
    {
        $response = $this->doRequest(
            self::RESOURCE_POST_MESSAGE_PATCH,
            $messagePatchToPost->serializeToString(),
            [
                'operationId' => $operationId
            ],
            self::METHOD_POST
        );

        $messagePatch = new MessagePatch();
        $messagePatch->mergeFromString($response);

        return $messagePatch;
    }

    /**
     * @param string|null $documentId
     *
     * @throws DiadocApiException
     */
    public function delete(string $boxId, string $messageId, ?string $documentId = null): bool
    {
        $this->doRequest(
            self::RESOURCE_DELETE,
            [],
            [
                'boxId' => $boxId,
                'messageId' => $messageId,
                'documentId' => $documentId
            ],
            self::METHOD_POST
        );

        return true;
    }

    /**
     * @throws DiadocApiException
     */
    public function forwardDocument(string $boxId, string $toBoxId, DocumentId $documentId): string
    {
        $forwardDocumentRequest = new ForwardDocumentRequest();
        $forwardDocumentRequest->setToBoxId($toBoxId);
        $forwardDocumentRequest->setDocumentId($documentId);

        return $this->doRequest(
            self::RESOURCE_FORWARD_DOCUMENT,
            $forwardDocumentRequest->serializeToString(),
            [
                'boxId' => $boxId
            ],
            self::METHOD_POST
        );
    }

    /**
     * @return Document| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getDocument(string $boxId, string $messageId, string $entityId, string $injectEntityContent = 'true'): Document
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_DOCUMENT,
            [],
            [
                'boxId' => $boxId,
                'messageId' => $messageId,
                'entityId'  => $entityId,
                'injectEntityContent'  => $injectEntityContent
            ]
        );
        $document = new Document();
        $document->mergeFromString($response);

        return $document;
    }



    public function getDocuments(string $boxId, ?DocumentsFilter $documentsFilter = null, ?int $sortDirection = null, ?int $afterIndexKey = null): DocumentList
    {
        if (is_null($sortDirection)) {
            $sortDirection = SortDirection::Ascending;
        }

        $params = [
            'boxId' => $boxId,
            'sortDirection' => $sortDirection,
            'afterIndexKey' => $afterIndexKey
        ];
        if (is_null($documentsFilter)) {
            $documentsFilter = DocumentsFilter::create();
        }

        $params = array_replace($params, $documentsFilter->toFilter());

        $response = $this->doRequest(
            self::RESOURCE_GET_DOCUMENTS,
            [],
            $params
        );

        $documentList = new DocumentList();
        $documentList->mergeFromString($response);

        return $documentList;
    }

    public function getDocumentTypes(string $boxId): GetDocumentTypesResponseV2
    {
        $params = [
            'boxId' => $boxId,
        ];

        $response = $this->doRequest(
            self::RESOURCE_GET_DOCUMENT_TYPES,
            [],
            $params
        );

        $getDocumentTypesResponseV2 = new GetDocumentTypesResponseV2();
        $getDocumentTypesResponseV2->mergeFromString($response);

        return $getDocumentTypesResponseV2;
    }


    /**
     *
     * @throws DiadocApiException
     */
    public function getDocflows(string $boxId, GetDocflowBatchRequest $getDocflowBatchRequest): GetDocflowBatchResponse
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_DOCFLOWS,
            $getDocflowBatchRequest->serializeToString(),
            [
                'boxId' => $boxId
            ],
            self::METHOD_POST
        );

        $getDocflowBatchResponse = new GetDocflowBatchResponse();
        $getDocflowBatchResponse->mergeFromString($response);

        return $getDocflowBatchResponse;
    }

    /**
     * @param null $afterIndexKey
     * @return GetDocflowsByPacketIdResponse| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function getDocflowsByPacketId(string $boxId, string $packetId, bool $injectEntityContent = false, ?int $afterIndexKey = null, int $count = 100): GetDocflowsByPacketIdResponse
    {
        $getDocflowsByPacketIdRequest = new GetDocflowsByPacketIdRequest();
        $getDocflowsByPacketIdRequest->setPacketId($packetId);
        $getDocflowsByPacketIdRequest->setInjectEntityContent($injectEntityContent);
        $getDocflowsByPacketIdRequest->setAfterIndexKey($afterIndexKey);
        $getDocflowsByPacketIdRequest->setCount($count);

        $response = $this->doRequest(
            self::RESOURCE_GET_DOCFLOWS_BY_PACKET_ID,
            $getDocflowsByPacketIdRequest->serializeToString(),
            [
                'boxId' => $boxId
            ],
            self::METHOD_POST
        );

        $getDocflowsByPacketIdResponse = new GetDocflowsByPacketIdResponse();
        $getDocflowsByPacketIdResponse->mergeFromString($response);

        return $getDocflowsByPacketIdResponse;
    }

    /**
     * @param int|null $searchScope
     * @param null|int $firstIndex
     * @return SearchDocflowsResponse| \Google\Protobuf\Internal\Message
     * @throws DiadocApiException
     */
    public function searchDocflows(string $boxId, string $queryString, ?int $searchScope = null, ?int $firstIndex = null, int $count = 100): SearchDocflowsResponse
    {
        $searchDocflowsRequest = new SearchDocflowsRequest();
        $searchDocflowsRequest->setQueryString($queryString);
        if ($searchScope) {
            $searchDocflowsRequest->setScope($searchScope);
        }

        if ($firstIndex) {
            $searchDocflowsRequest->setFirstIndex($firstIndex);
        }

        $searchDocflowsRequest->setCount($count);

        $response = $this->doRequest(
            self::RESOURCE_SEARCH_DOCFLOWS,
            $searchDocflowsRequest->serializeToString(),
            [
                'boxId' => $boxId
            ],
            self::METHOD_POST
        );

        $searchDocflowsResponse = new SearchDocflowsResponse();
        $searchDocflowsResponse->mergeFromString($response);

        return $searchDocflowsResponse;
    }

    /**
     * @param DateTime|null $from
     * @param DateTime|null $to
     * @throws DiadocApiException
     */
    public function getDocflowEvents(
        string $boxId,
        ?DateTime $from = null,
        ?DateTime $to = null,
        ?int $sortDirection = null,
        bool $populateDocuments = false,
        bool $populatePreviousDocumentStates = false,
        bool $injectEntityContent = false,
        ?int $afterIndexKey = null
    ): GetDocflowEventsResponse {
        $timeBasedFilter = new TimeBasedFilter();
        $fromTimestamp = null;
        $toTimestamp = null;

        if ($from instanceof \DateTime) {
            $fromTimestamp = new Timestamp();
            $fromTimestamp->setTicks(DateHelper::convertDateTimeToTicks($from));
        }

        if ($to instanceof \DateTime) {
            $toTimestamp = new Timestamp();
            $toTimestamp->setTicks(DateHelper::convertDateTimeToTicks($to));
        }

        $timeBasedFilter->setFromTimestamp($fromTimestamp);
        $timeBasedFilter->setToTimestamp($toTimestamp);
        $timeBasedFilter->setSortDirection($sortDirection);

        $getDocflowEventsRequest = new GetDocflowEventsRequest();
        $getDocflowEventsRequest->setFilter($timeBasedFilter);
        $getDocflowEventsRequest->setPopulateDocuments($populateDocuments);
        $getDocflowEventsRequest->setPopulatePreviousDocumentStates($populatePreviousDocumentStates);
        $getDocflowEventsRequest->setInjectEntityContent($injectEntityContent);
        $getDocflowEventsRequest->setAfterIndexKey($afterIndexKey);


        $response = $this->doRequest(
            self::RESOURCE_GET_DOCFLOWS_EVENTS,
            $getDocflowEventsRequest->serializeToString(),
            [
                'boxId' => $boxId
            ],
            self::METHOD_POST
        );

        $getDocflowEventsResponse = new GetDocflowEventsResponse();
        $getDocflowEventsResponse->mergeFromString($response);

        return $getDocflowEventsResponse;
    }


    /**
     * @throws DiadocApiException
     */
    public function getEvent(string $boxId, string $eventId): BoxEvent
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_EVENT,
            [],
            [
                'boxId' => $boxId,
                'eventId' => $eventId
            ]
        );

        $boxEvent = new BoxEvent();
        $boxEvent->mergeFromString($response);

        return $boxEvent;
    }

    public function getNewEvents(string $boxId, ?string $afterEventId = null): BoxEventList
    {
        $response = $this->doRequest(
            self::RESOURCE_GET_NEW_EVENTS,
            [],
            [
                'boxId' => $boxId,
                'afterEventId' => $afterEventId
            ]
        );
        $boxEventList = new BoxEventList();
        $boxEventList->mergeFromString($response);

        return $boxEventList;
    }

    protected function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        if ($token === null || $token === '') {
            $this->token = null;
            $this->refreshToken = null;
            $this->accessTokenExpiresAt = null;

            return;
        }
        $this->token = $this->normalizeAuthTokenResponse($token);
        $this->token = $this->sanitizeForHttpHeader($this->token);
        if ($this->token === '') {
            $this->token = null;
            $this->refreshToken = null;
            $this->accessTokenExpiresAt = null;

            return;
        }
        // Только access: отключаем проактивный refresh (нет срока и refresh в связке)
        $this->refreshToken = null;
        $this->accessTokenExpiresAt = null;
    }

    public function generateInvitationDocument(string $content, string $title, bool $signatureRequested = false): InvitationDocument
    {
        $invitationDocument = new InvitationDocument();
        $invitationDocument->setFileName($title);
        $invitationDocument->setSignedContent($this->generateSignedContent($content));
        $invitationDocument->setSignatureRequested($signatureRequested);

        return $invitationDocument;
    }

    public function generateSignedContentFromFile(string $fileName): SignedContent
    {
        if (!file_exists($fileName)) {
            throw new \Exception('File not found');
        }

        $content = file_get_contents($fileName);

        return $this->generateSignedContent($content);
    }

    public function generateSignedContent(string $content): SignedContent
    {
        $signedContent = new SignedContent();
        $signedContent->setContent($content);
        $signedContent->setSignature($this->signerProvider->sign($content));

        return $signedContent;
    }


    // В документации не описано до конца как делать. Вот есть решение в issues
    // https://github.com/diadoc/diadocapi-docs/issues/323
    public function shelfUpload(string $nameOnShelf, int $partIndex, string $content, int $isLastPart): string
    {
        return $this->doRequest(
            self::RESOURCE_SHELF_UPLOAD,
            ['content' => $content],
            [
                'nameOnShelf' => $nameOnShelf,
                'partIndex' => $partIndex,
                'isLastPart'    => $isLastPart,
            ],
            self::METHOD_POST,
            self::CONTENT_FORM_URL_ENCODED
        );
    }
}
