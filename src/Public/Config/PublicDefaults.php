<?php
namespace TalerPayments\Public\Config;

/**
 * Shared defaults for public payment flows.
 */
final class PublicDefaults
{
    public const AMOUNT = 'KUDOS:1.00';
    public const SUMMARY = 'Donation';
    public const MAX_SUMMARY_LENGTH = 255;
}
