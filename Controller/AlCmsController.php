<?php
/*
 * This file is part of the AlphaLemon CMS Application and it is distributed
 * under the GPL LICENSE Version 2.0. To use this application you must leave
 * intact this copyright notice.
 *
 * Copyright (c) AlphaLemon <webmaster@alphalemon.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * For extra documentation and help please visit http://www.alphalemon.com
 *
 * @license    GPL LICENSE Version 2.0
 *
 */

namespace AlphaLemon\AlphaLemonCmsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Finder\Finder;

use AlphaLemon\AlphaLemonCmsBundle\Core\Model\AlBlockQuery;
use AlphaLemon\AlphaLemonCmsBundle\Core\Model\AlPageQuery;

use AlphaLemon\ThemeEngineBundle\Core\Model\AlThemeQuery;
use AlphaLemon\AlphaLemonCmsBundle\Core\Model\AlLanguageQuery;
use AlphaLemon\PageTreeBundle\Core\Tools\AlToolkit;
use AlphaLemon\AlphaLemonCmsBundle\Core\Form\ModelChoiceValues\ChoiceValues;

use AlphaLemon\AlphaLemonCmsBundle\Core\Assetic\AlAsseticDynamicFileManager\AlAsseticDynamicFileManagerJs;
use AlphaLemon\AlphaLemonCmsBundle\Core\Assetic\AlAsseticDynamicFileManager\AlAsseticDynamicFileManagerCss;


use AlphaLemon\ThemeEngineBundle\Core\Event\PageRenderer\BeforePageRenderingEvent;
use AlphaLemon\ThemeEngineBundle\Core\Event\PageRendererEvents;
use AlphaLemon\AlphaLemonCmsBundle\Core\PageTree\AlPageTree;

/**
 * Implements the controller to load AlphaLemon CMS
 *
 * @author alphalemon <webmaster@alphalemon.com>
 */
class AlCmsController extends Controller
{
    private $cmsAssets = array();
    private $kernel = null;

    public function showAction()
    {
        $this->kernel = $this->container->get('kernel');
        $pageTree = $this->container->get('al_page_tree');
        $isSecure = (null !== $this->get('security.context')->getToken()) ? true : false;
        $skin = AlToolkit::retrieveBundleWebFolder($this->kernel, 'AlphaLemonCmsBundle') . '/css/skins/' . $this->container->getParameter('alcms.skin');

        $params = array('template' => 'AlphaLemonCmsBundle:Cms:welcome.html.twig',
                        'templateStylesheets' => null,
                        'templateJavascripts' => null,
                        'available_blocks' => null,
                        'internal_stylesheets' => null,
                        'internal_javascripts' => null,
                        'skin_path' => $skin,
                        'is_secure' => $isSecure,
                        'pages' => ChoiceValues::getPages($this->container->get('page_model')),
                        'languages' => ChoiceValues::getLanguages($this->container->get('language_model')),
                        'page' => 0,
                        'language' => 0,
                        'available_languages' => $this->container->getParameter('alcms.available_languages'),
                        'frontController' => sprintf('/%s.php/', $this->kernel->getEnvironment()),);

        if(null !== $pageTree)
        {
            $pageTree = $this->dispatchEvents($pageTree);
            $template = $this->findTemplate($pageTree);
            $availableBlocks = $this->findAvailableBlocks();

            $params = array_merge($params, array(
                                'metatitle' => $pageTree->getMetatitle(),
                                'metadescription' => $pageTree->getMetaDescription(),
                                'metakeywords' => $pageTree->getMetaKeywords(),
                                'internal_stylesheets' => $pageTree->getInternalStylesheet(),
                                'internal_javascripts' => $pageTree->getInternalJavascript(),
                                'values' => $pageTree->getContents(),
                                'template' => $template,
                                'page' => (null != $pageTree->getAlPage()) ? $pageTree->getAlPage()->getId() : 0,
                                'language' => (null != $pageTree->getAlLanguage()) ? $pageTree->getAlLanguage()->getId() : 0,
                                'available_blocks' => $availableBlocks,
                                'base_template' => $this->container->getParameter('althemes.base_template'),
                                'templateStylesheets' => $this->locateAssets($pageTree->getExternalStylesheets()),
                                'templateJavascripts' => $this->locateAssets($pageTree->getExternalJavascripts()),
                                ));
        }
        else
        {
            $this->get('session')->setFlash('message', 'The requested page has not been loaded.');
        }

        return $this->render('AlphaLemonCmsBundle:Cms:index.html.twig', $params);
    }

    private function dispatchEvents(AlPageTree $pageTree)
    {
        $request = $this->container->get('request');
        $dispatcher = $this->container->get('event_dispatcher');

        $event = new BeforePageRenderingEvent($this->container->get('request'), $pageTree);
        $dispatcher->dispatch(PageRendererEvents::BEFORE_RENDER_PAGE, $event);
        if ($pageTree != $event->getPageTree()) {
            $pageTree = $event->getPageTree();
        }

        $eventName = sprintf('page_renderer.before_%s_rendering', $request->attributes->get('_locale'));
        $dispatcher->dispatch($eventName, $event);
        if ($pageTree != $event->getPageTree()) {
            $pageTree = $event->getPageTree();
        }

        $eventName = sprintf('page_renderer.before_%s_rendering', $request->get('page'));
        $dispatcher->dispatch($eventName, $event);
        if ($pageTree != $event->getPageTree()) {
            $pageTree = $event->getPageTree();
        }

        return $pageTree;
    }

    private function findTemplate(AlPageTree $pageTree)
    {
        $template = 'AlphaLemonCmsBundle:Cms:welcome.html.twig';

        $themeFolder = AlToolkit::locateResource($this->kernel, $pageTree->getThemeName());
        if(false === $themeFolder || !is_file($themeFolder .'Resources/views/Theme/' . $pageTree->getTemplateName() . '.html.twig'))
        {
            $this->get('session')->setFlash('message', 'The template assigned to this page does not exist. This appens when you change a theme with a different number of templates from the active one. To fix this issue you shoud activate the previous theme again and change the pages which cannot be rendered by this theme');

            return $template;
        }

        if($pageTree->getThemeName() != "" && $pageTree->getTemplateName() != "")
        {
            $this->kernelPath = $this->container->getParameter('kernel.root_dir');
            $template = (is_file(sprintf('%s/Resources/views/%s/%s.html.twig', $this->kernelPath, $pageTree->getThemeName(), $pageTree->getTemplateName()))) ? sprintf('::%s/%s.html.twig', $pageTree->getThemeName(), $pageTree->getTemplateName()) : sprintf('%s:Theme:%s.html.twig', $pageTree->getThemeName(), $pageTree->getTemplateName());
        }

        return $template;
    }

    private function findAvailableBlocks()
    {
        $availableBlocks = array();
        foreach ($this->kernel->getBundles() as $bundle)
        {
            if(method_exists($bundle, 'getAlphaLemonBundleDescription'))
            {
                $bundleName = preg_replace('/Bundle$/', '', $bundle->getName());
                $availableBlocks[$bundleName] = $bundle->getAlphaLemonBundleDescription();
            }
        }

        return $availableBlocks;
    }


    private function locateAssets(array $assets)
    {
        $located = array();
        foreach($assets as $asset)
        {
            $filename = basename($asset);
            if(in_array($filename, $this->cmsAssets))
            {
                continue;
            }

            $currentAsset = $asset;

            // Checks if the assets is given with a relative path
            if(false !== strpos($currentAsset, 'bundles') || false !== strpos($currentAsset, '@'))
            {
                preg_match('/^@([\w]+Bundle)\/Resources\/public\/([\w\/\.-]+)/', $currentAsset, $match);
                if(!empty($match))
                {
                        $currentAsset = AlToolkit::retrieveBundleWebFolder($this->kernel, $match[1]) . '/' . $match[2];
                }

                $currentAsset = AlToolkit::normalizePath($currentAsset);
                $located[] =  $currentAsset;
            }
        }

        return $located;
    }

    /**
     * TODO : removable?
     */
    private function retrieveCmsAssets()
    {
        $alCmsAssetsFolders = array('js/vendor/jquery', 'js/vendor', );
        $resourcesPath = AlToolkit::locateResource($this->kernel, '@AlphaLemonThemeEngineBundle/Resources/public');
        $assets = $this->process($resourcesPath, $alCmsAssetsFolders);

        $alCmsAssetsFolders = array('js/vendor/medialize', 'js', );
        $resourcesPath = AlToolkit::locateResource($this->kernel, '@AlphaLemonCmsBundle/Resources/public');
        $assets = array_merge($assets, $this->process($resourcesPath, $alCmsAssetsFolders));

        return $assets;
    }

    /**
     * TODO : removable?
     */
    private function process($resourcesPath, $alCmsAssetsFolders)
    {
        $assets = array();
        foreach($alCmsAssetsFolders as $alCmsAssetsFolder)
        {
            $finder = new Finder();
            $finder->files()->depth(0)->in($resourcesPath . "/" .$alCmsAssetsFolder);
            foreach($finder as $asset)
            {
                $assets[] = basename($asset->getFileName());
            }
        }

        return $assets;
    }
}

