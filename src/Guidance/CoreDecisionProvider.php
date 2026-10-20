<?php

declare(strict_types=1);

/**
 * OpenDXP
 *
 * This source file is licensed under the GNU General Public License version 3 (GPLv3).
 *
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) OpenDXP (https://www.opendxp.io)
 * @license    https://www.gnu.org/licenses/gpl-3.0.html  GNU General Public License version 3 (GPLv3)
 */

namespace OpenDxp\Bundle\McpBundle\Guidance;

use OpenDxp\Config;
use OpenDxp\Config\LocationAwareConfigRepository;
use OpenDxp\Extension\Document\Areabrick\AreabrickInterface;
use OpenDxp\Extension\Document\Areabrick\AreabrickManagerInterface;
use OpenDxp\Model\Asset;
use OpenDxp\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyObjectRelation;
use OpenDxp\Model\DataObject\ClassDefinition\Data\AdvancedManyToManyRelation;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Block;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Classificationstore;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Fieldcollections;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Hotspotimage;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Image;
use OpenDxp\Model\DataObject\ClassDefinition\Data\ImageGallery;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Link;
use OpenDxp\Model\DataObject\ClassDefinition\Data\ManyToManyObjectRelation;
use OpenDxp\Model\DataObject\ClassDefinition\Data\ManyToManyRelation;
use OpenDxp\Model\DataObject\ClassDefinition\Data\ManyToOneRelation;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Objectbricks;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Relations\AbstractRelations;
use OpenDxp\Model\DataObject\ClassDefinition\Data\ReverseObjectRelation;
use OpenDxp\Model\DataObject\ClassDefinition\Data\Video;
use OpenDxp\Model\Document\Editable\Area;
use OpenDxp\Model\Document\Editable\Areablock;
use OpenDxp\Model\Document\Editable\Block as BlockEditable;
use OpenDxp\Model\Document\Editable\Scheduledblock;
use OpenDxp\Model\Metadata\Predefined as PredefinedMetadata;
use OpenDxp\Model\Property;
use OpenDxp\Model\Property\Predefined as PredefinedProperty;
use OpenDxp\Model\WebsiteSetting;
use function array_key_exists;
use function implode;
use function in_array;
use function is_subclass_of;
use function ltrim;
use function sort;
use function sprintf;

final readonly class CoreDecisionProvider implements DecisionProviderInterface
{
    private const string DOCUMENTATION = 'https://docs.opendxp.io/docs/core-framework/%s/';

    private const array DOCUMENT_TYPES = [
        'page'     => 'The content has a URL of its own and is rendered by a controller and a template.',
        'snippet'  => 'The content is reused inside pages or other snippets and has no URL of its own.',
        'link'     => 'An entry of the navigation points at another document or an external URL.',
        'hardlink' => 'An existing document tree appears again in another place or site without being copied.',
        'email'    => 'The content is an email that editors maintain like a page.',
        'folder'   => 'Documents are only grouped. A folder has no content of its own.',
    ];

    private const array RELATIONS = [
        'manyToOneRelation',
        'manyToManyRelation',
        'advancedManyToManyRelation',
        'manyToManyObjectRelation',
        'advancedManyToManyObjectRelation',
        'reverseObjectRelation',
    ];

    public function __construct(private AreabrickManagerInterface $areabrickManager)
    {
    }

    public function getDecisions(): array
    {
        return [
            $this->getObjectFieldGroup(),
            $this->getObjectRelation(),
            $this->getEditorValue(),
            $this->getConfigStorage(),
            $this->getPageContent(),
            $this->getDocumentType(),
        ];
    }

    private function getObjectFieldGroup(): Decision
    {
        return new Decision(
            id: 'object_field_group',
            question: 'How does a data object class hold a group of fields that repeats or differs between objects?',
            options: [
                new Option(
                    name: 'Field collection',
                    when: 'An object holds any number of items, possibly of several collection types. Each type has a table of its own, so the items can be queried.',
                    symbol: Fieldcollections::class,
                ),
                new Option(
                    name: 'Object brick',
                    when: 'Objects of one class need different attributes by category, such as tires and brakes in one product class. An object holds at most one brick of each type. Bricks support almost every data type, relations included, and a listing filters them with a join.',
                    symbol: Objectbricks::class,
                ),
                new Option(
                    name: 'Classification store',
                    when: 'There are many categories with many attributes, roughly more than 30, or an import creates the attributes. Only simple types are allowed, no relations. The data is stored as EAV, which makes filtering tedious.',
                    symbol: Classificationstore::class,
                ),
                new Option(
                    name: 'Block',
                    when: 'An object holds any number of items and nothing ever queries them. The whole block is serialized into one column.',
                    symbol: Block::class,
                ),
            ],
            documentation: sprintf(self::DOCUMENTATION, 'Best_Practice/Object_Bricks_vs_Classification_Store'),
        );
    }

    private function getObjectRelation(): Decision
    {
        $options = [
            new Option(
                name: 'manyToOneRelation',
                when: 'The field points at a single element. It may be a document, an asset or an object, and the field restricts the allowed types and classes.',
                symbol: ManyToOneRelation::class,
            ),
            new Option(
                name: 'manyToManyRelation',
                when: 'The field points at several elements, and their types may be mixed.',
                symbol: ManyToManyRelation::class,
            ),
            new Option(
                name: 'advancedManyToManyRelation',
                when: 'The field points at several elements of any type, and each relation carries data of its own in metadata columns. The getter returns ElementMetadata.',
                symbol: AdvancedManyToManyRelation::class,
            ),
            new Option(
                name: 'manyToManyObjectRelation',
                when: 'The field points at several data objects and nothing else. The field restricts the allowed classes.',
                symbol: ManyToManyObjectRelation::class,
            ),
            new Option(
                name: 'advancedManyToManyObjectRelation',
                when: 'The field points at several data objects of exactly one class, and each relation carries data of its own, such as the share of a material in a product. The editor shows fields of the related objects next to the metadata columns. The getter returns ObjectMetadata.',
                symbol: AdvancedManyToManyObjectRelation::class,
            ),
            new Option(
                name: 'reverseObjectRelation',
                when: 'A relation field of another class owns the relation, and this class should show and edit it as well. The field stores nothing. Code sets the relation on the owner.',
                symbol: ReverseObjectRelation::class,
            ),
            new Option(
                name: 'image',
                when: 'The field holds a single image asset. It is stored as an id column, not in the relations table.',
                symbol: Image::class,
            ),
            new Option(
                name: 'hotspotimage',
                when: 'The field holds a single image asset, and the editor sets hotspots, markers or a crop on it.',
                symbol: Hotspotimage::class,
            ),
            new Option(
                name: 'imageGallery',
                when: 'The field holds several images in an order the editor sets. Each image can have hotspots, markers and a crop.',
                symbol: ImageGallery::class,
            ),
            new Option(
                name: 'video',
                when: 'The field holds a video. It is a video asset or a video on YouTube, Vimeo or Dailymotion.',
                symbol: Video::class,
            ),
            new Option(
                name: 'link',
                when: 'The field holds a link with a text and a target. The target is a document, an asset, an object or an external URL.',
                symbol: Link::class,
            ),
        ];

        $map = Config::getSystemConfiguration('objects')['class_definitions']['data']['map'] ?? [];

        foreach ($map as $type => $class) {
            $class = ltrim((string) $class, '\\');

            if (!in_array($type, self::RELATIONS, true) && is_subclass_of($class, AbstractRelations::class)) {
                $options[] = new Option(
                    name: $type,
                    when: 'A relation type that this installation adds. The bundle that adds it documents when it fits.',
                    symbol: $class,
                );
            }
        }

        return new Decision(
            id: 'object_relation',
            question: 'Which field type holds a reference of a data object to a document, an asset or another object?',
            options: $options,
            documentation: sprintf(self::DOCUMENTATION, 'Objects/Object_Classes/Data_Types/Relation_Types'),
            note: 'The relation types load lazily, when their getter is called. A listing filters a many-to-one relation with "field__id = <id>". It filters a many-to-many relation with "field LIKE \'%,<type>|<id>,%\'", where the type is object, asset or document.',
        );
    }

    private function getEditorValue(): Decision
    {
        return new Decision(
            id: 'editor_value',
            question: 'Where does an editor set a value that the code reads, outside the fields of a class and the editables of a page?',
            options: [
                new Option(
                    name: 'Website setting',
                    when: 'The value holds for the whole installation, optionally per site or per language. Examples are an API key or a default.',
                    symbol: WebsiteSetting::class,
                ),
                new Option(
                    name: 'Property',
                    when: 'The value belongs to a single document, asset or object, such as a flag that hides the navigation. A property marked inheritable also applies to the children.',
                    symbol: Property::class,
                    note: sprintf('A predefined property offers the property to the editors with a default value. %s', PredefinedProperty::class),
                ),
                new Option(
                    name: 'Asset metadata',
                    when: 'The value describes an asset, such as a title, an alt text or a copyright. It can differ per language and has a type, which may be a relation to another element.',
                    symbol: Asset::class,
                    note: sprintf('Predefined asset metadata offers a name, a type and a default to the editors. The configuration opendxp.assets.metadata names the metadata that the frontend uses for alt, title and copyright. %s', PredefinedMetadata::class),
                ),
            ],
            documentation: sprintf(self::DOCUMENTATION, 'Tools_and_Features/Properties'),
        );
    }

    private function getConfigStorage(): Decision
    {
        return new Decision(
            id: 'config_storage',
            question: 'Where is a configuration stored that is edited in the admin, such as thumbnails or document types?',
            options: [
                new Option(
                    name: 'symfony-config',
                    when: 'The configuration belongs to the code. It is written as YAML into the project and deployed with it. Production only picks up a change in debug mode, so it is read-only there.',
                    symbol: LocationAwareConfigRepository::class,
                ),
                new Option(
                    name: 'settings-store',
                    when: 'Editors change the configuration in production. It is written to the database and is not deployed with the code.',
                    symbol: LocationAwareConfigRepository::class,
                ),
                new Option(
                    name: 'disabled',
                    when: 'Nobody edits the configuration in the admin. It only comes from YAML that a developer writes.',
                    symbol: LocationAwareConfigRepository::class,
                ),
            ],
            documentation: sprintf(self::DOCUMENTATION, 'Deployment/Configuration_Environments'),
            note: 'Each type has its own write_target. Every bundle can add types. site_config with the section config_locations lists the types of this installation, the key that sets the target and the current target.',
        );
    }

    private function getPageContent(): Decision
    {
        $bricks = $this->areabrickManager->getBrickIds();
        sort($bricks);

        return new Decision(
            id: 'page_content',
            question: 'How does an editor fill an area of a page?',
            options: [
                new Option(
                    name: 'An editable in the template',
                    when: 'The template fixes the layout and the editor only fills in the content.',
                ),
                new Option(
                    name: 'A block',
                    when: 'The editor repeats the same set of editables as often as needed.',
                    symbol: BlockEditable::class,
                ),
                new Option(
                    name: 'A scheduled block',
                    when: 'The editor prepares several versions of the same editables, each with a date. The frontend shows only the version whose date applies.',
                    symbol: Scheduledblock::class,
                ),
                new Option(
                    name: 'An areablock',
                    when: 'The editor chooses from several kinds of content and arranges them. Each kind is an areabrick, a class with a template of its own that every page can reuse.',
                    symbol: Areablock::class,
                ),
                new Option(
                    name: 'An area',
                    when: 'One areabrick sits at a fixed place, and the template chooses it. The editor fills it but cannot swap it.',
                    symbol: Area::class,
                ),
            ],
            documentation: sprintf(self::DOCUMENTATION, 'Documents/Editables/Areablock'),
            note: [] !== $bricks
                ? sprintf('This installation registers these areabricks: %s. A new one implements %s.', implode(', ', $bricks), AreabrickInterface::class)
                : sprintf('This installation registers no areabricks. A new one implements %s.', AreabrickInterface::class),
        );
    }

    private function getDocumentType(): Decision
    {
        $options = [];

        foreach (Config::getSystemConfiguration('documents')['type_definitions']['map'] ?? [] as $type => $definition) {
            $facts = [
                array_key_exists($type, self::DOCUMENT_TYPES) ? self::DOCUMENT_TYPES[$type] : 'A type that this installation adds.',
            ];

            if ($definition['direct_route'] ?? false) {
                $facts[] = 'It can have a URL of its own.';
            }

            if (!($definition['children_supported'] ?? true)) {
                $facts[] = 'It cannot have children.';
            }

            if ($definition['only_printable_childrens'] ?? false) {
                $facts[] = 'Its children can only be printable documents.';
            }

            if ($definition['predefined_document_types'] ?? false) {
                $facts[] = 'Editors can start it from a predefined document type.';
            }

            $options[] = new Option(
                name: $type,
                when: implode(' ', $facts),
                symbol: isset($definition['class']) ? ltrim((string) $definition['class'], '\\') : null,
            );
        }

        return new Decision(
            id: 'document_type',
            question: 'Which document type fits the content?',
            options: $options,
            documentation: sprintf(self::DOCUMENTATION, 'Documents'),
            note: 'The list comes from opendxp.documents.type_definitions of this installation. A project or a bundle can add types there.',
        );
    }
}
