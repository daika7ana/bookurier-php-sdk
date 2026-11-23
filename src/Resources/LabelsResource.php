<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Resources;

use Daika7ana\Bookurier\Client;
use Daika7ana\Bookurier\Exceptions\AuthenticationException;
use Daika7ana\Bookurier\Exceptions\TransportException;
use Daika7ana\Bookurier\Exceptions\UnexpectedStatusException;
use Daika7ana\Bookurier\Exceptions\ValidationException;
use Daika7ana\Bookurier\Support\Document;
use Daika7ana\Bookurier\Support\ResponseBody;
use Daika7ana\Bookurier\Support\Xml;

final class LabelsResource
{
    public function __construct(
        private Client $client,
    ) {}

    /**
     * AWB label as HTML, by AWB.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function html(string $awb): Document
    {
        return ResponseBody::document(
            $this->client->post('get_AWB.php', ['msg' => Xml::message(Xml::cmd(['awb' => $awb]))]),
        );
    }

    /**
     * AWB label as PDF, by AWB.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function pdf(string $awb): Document
    {
        return ResponseBody::document(
            $this->client->post('get_AWB_pdf.php', ['msg' => Xml::message(Xml::cmd(['awb' => $awb]))]),
            'pdf',
        );
    }

    /**
     * AWB label as HTML, by the caller's own identifier.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function htmlByUniqueId(string $uniqueId): Document
    {
        return ResponseBody::document(
            $this->client->post('get_AWB_unq.php', ['msg' => Xml::message(Xml::cmd(['unq' => $uniqueId]))]),
        );
    }

    /**
     * AWB label as PDF, by the caller's own identifier.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function pdfByUniqueId(string $uniqueId): Document
    {
        return ResponseBody::document(
            $this->client->post('get_AWB_unq_pdf.php', ['msg' => Xml::message(Xml::cmd(['unq' => $uniqueId]))]),
            'pdf',
        );
    }

    /**
     * Labels for every order of the day, in one HTML document.
     *
     * @throws ValidationException
     * @throws AuthenticationException
     * @throws TransportException
     * @throws UnexpectedStatusException
     */
    public function daily(): Document
    {
        return ResponseBody::document($this->client->post('get_AWB_list.php'));
    }
}
