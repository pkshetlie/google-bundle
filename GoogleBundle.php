<?php

namespace GoogleBundle;

use GoogleBundle\DependencyInjection\GoogleExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class GoogleBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new GoogleExtension();
    }
}
