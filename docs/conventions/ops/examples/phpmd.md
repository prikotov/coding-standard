# Пример phpmd.xml

> Пример конфигурации PHP Mess Detector для проекта на Symfony.
> Полное описание suppressions и обоснований — в [phpmd-suppressions-guidelines.md](../phpmd-suppressions-guidelines.md).

```xml
<?xml version="1.0"?>
<ruleset name="Project PHPMD Ruleset"
         xmlns="https://phpmd.org/schema/phpmd.xsd">
    <description>PHPMD configuration for Symfony project.</description>

    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/var/*</exclude-pattern>
    <exclude-pattern>*/bin/*</exclude-pattern>
    <exclude-pattern>*/assets/*</exclude-pattern>
    <exclude-pattern>*/public/*</exclude-pattern>
    <exclude-pattern>*/translations/*</exclude-pattern>
    <exclude-pattern>*/apps/*</exclude-pattern>
    <exclude-pattern>*/tests/*</exclude-pattern>

    <rule ref="rulesets/codesize.xml">
        <exclude name="CyclomaticComplexity"/>
        <exclude name="NPathComplexity"/>
        <exclude name="ExcessiveMethodLength"/>
        <exclude name="ExcessiveClassLength"/>
        <exclude name="ExcessiveClassComplexity"/>
        <exclude name="ExcessiveParameterList"/>
        <exclude name="TooManyFields"/>
        <exclude name="TooManyMethods"/>
    </rule>
    <rule ref="rulesets/codesize.xml/CyclomaticComplexity">
        <properties>
            <property name="reportLevel" value="25"/>
            <property name="showClassesComplexity" value="true"/>
            <property name="showMethodsComplexity" value="true"/>
        </properties>
    </rule>
    <rule ref="rulesets/codesize.xml/NPathComplexity">
        <properties>
            <property name="minimum" value="100000000000"/>
        </properties>
    </rule>
    <rule ref="rulesets/codesize.xml/ExcessiveMethodLength">
        <properties>
            <property name="minimum" value="80"/>
            <property name="ignore-whitespace" value="false"/>
        </properties>
    </rule>
    <rule ref="rulesets/codesize.xml/ExcessiveClassLength">
        <properties>
            <property name="minimum" value="500"/>
            <property name="ignore-whitespace" value="false"/>
        </properties>
    </rule>
    <rule ref="rulesets/codesize.xml/ExcessiveParameterList">
        <properties>
            <property name="minimum" value="62"/>
        </properties>
    </rule>
    <rule ref="rulesets/codesize.xml/TooManyFields">
        <properties>
            <property name="maxfields" value="12"/>
        </properties>
    </rule>
    <rule ref="rulesets/codesize.xml/TooManyMethods">
        <properties>
            <property name="maxmethods" value="20"/>
            <property name="ignorepattern" value="(^(set|get|is|has|with))i"/>
        </properties>
    </rule>
    <rule ref="rulesets/cleancode.xml">
        <exclude name="BooleanArgumentFlag"/>
        <exclude name="StaticAccess"/>
    </rule>
    <rule ref="rulesets/cleancode.xml/StaticAccess">
        <properties>
            <!-- Добавьте FQCN stateless-классов, для которых статический вызов обоснован -->
            <property name="exceptions" value="Symfony\Component\Uid\Uuid,Symfony\Component\Mime\MimeTypes,Firebase\JWT\JWT"/>
            <property name="ignorepattern" value="/^(create|createFrom|from|tryFrom|cases|normalize|parse|build|format|clean)/"/>
        </properties>
    </rule>
    <rule ref="rulesets/design.xml">
        <exclude name="NumberOfChildren"/>
        <exclude name="DepthOfInheritance"/>
        <exclude name="CouplingBetweenObjects"/>
    </rule>
    <rule ref="rulesets/design.xml/CouplingBetweenObjects">
        <properties>
            <property name="maximum" value="36"/>
        </properties>
    </rule>
    <rule ref="rulesets/design.xml/NumberOfChildren">
        <properties>
            <property name="minimum" value="10"/>
        </properties>
    </rule>
    <rule ref="rulesets/design.xml/DepthOfInheritance">
        <properties>
            <property name="minimum" value="4"/>
        </properties>
    </rule>
    <rule ref="rulesets/naming.xml">
        <exclude name="LongClassName"/>
        <exclude name="ShortVariable"/>
        <exclude name="LongVariable"/>
    </rule>
    <rule ref="rulesets/naming.xml/ShortVariable">
        <properties>
            <property name="minimum" value="3"/>
            <property name="exceptions" value="id,qb,ip,em,at,to"/>
            <property name="allow-short-variables-in-loop" value="true"/>
        </properties>
    </rule>
    <rule ref="rulesets/naming.xml/LongClassName">
        <properties>
            <property name="maximum" value="45"/>
            <property name="subtract-suffixes" value="Interface,Service,Repository,Handler,Listener,Mapper,Specification,Command,Query,Event,Model,Dto,Enum"/>
        </properties>
    </rule>
    <rule ref="rulesets/naming.xml/LongVariable">
        <properties>
            <property name="maximum" value="45"/>
        </properties>
    </rule>
    <rule ref="rulesets/unusedcode.xml"/>
</ruleset>
```
