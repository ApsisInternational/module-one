<?php

namespace Apsis\One\Service\Sub;

class SubConfigService
{
    /**
     * @var array
     */
    private array $config = [];

    /**
     * @param array $config
     *
     * @return $this
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return string|null
     */
    public function getAccountId(): ?string
    {
        if (isset($this->config['account_id'])) {
            return (string) $this->config['account_id'];
        }
        return null;
    }

    /**
     * @return int|null
     */
    public function getSectionId(): ?int
    {
        if (isset($this->config['section_id'])) {
            return (int) $this->config['section_id'];
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getClientId(): ?string
    {
        if (isset($this->config['one_api_key']['client_id'])) {
            return (string) $this->config['one_api_key']['client_id'];
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        if (isset($this->config['one_api_key']['client_secret'])) {
            return (string) $this->config['one_api_key']['client_secret'];
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getApiUrl(): ?string
    {
        if (isset($this->config['api_base_url'])) {
            return (string) $this->config['api_base_url'];
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getSectionDiscriminator(): ?string
    {
        if (isset($this->config['section_discriminator'])) {
            return (string) $this->config['section_discriminator'];
        }
        return null;
    }

    /**
     * @return string|null
     */
    public function getKeyspaceDiscriminator(): ?string
    {
        if (isset($this->config['keyspace_discriminator'])) {
            return (string) $this->config['keyspace_discriminator'];
        }
        return null;
    }
}
