<?php

namespace Arya;

/**
 * An internal exception used for app termination without calling die() or exit()
 */
class TerminationException extends \Exception {}