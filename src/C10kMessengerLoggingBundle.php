<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle;

use C10k\MessengerLoggingBundle\DependencyInjection\Compiler\RegisterStampNormalizersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class C10kMessengerLoggingBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterStampNormalizersPass());
    }
}
