<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;

/* sql/relational_column_dropdown.twig */
class __TwigTemplate_23e1e6358b1f0efdc6dd0ca31d4e0a7f extends Template
{
    private $source;
    private $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
    }

    protected function doDisplay(array $context, array $blocks = [])
    {
        $macros = $this->macros;
        // line 1
        yield "<span class=\"curr_value\">";
        yield $this->env->getRuntime('Twig\Runtime\EscaperRuntime')->escape(($context["current_value"] ?? null), "html", null, true);
        yield "</span>
<a href=\"";
        // line 2
        yield PhpMyAdmin\Url::getFromRoute("/browse-foreigners");
        yield "\" data-post=\"";
        yield PhpMyAdmin\Url::getCommon(($context["params"] ?? null), "", false);
        yield "\" class=\"ajax browse_foreign\">
  ";
yield _gettext("Browse foreign values");
        // line 4
        yield "</a>
";
        return; yield '';
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName()
    {
        return "sql/relational_column_dropdown.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable()
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo()
    {
        return array (  50 => 4,  43 => 2,  38 => 1,);
    }

    public function getSourceContext()
    {
        return new Source("", "sql/relational_column_dropdown.twig", "D:\\automasition\\public\\phpMyAdmin-5.2.2-english\\templates\\sql\\relational_column_dropdown.twig");
    }
}
