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

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;

use AlphaLemon\ThemeEngineBundle\Core\ThemeManager\AlThemeManager;
use AlphaLemon\AlphaLemonCmsBundle\Core\Repository\AlLanguageQuery;
use AlphaLemon\AlphaLemonCmsBundle\Core\Repository\AlPageQuery;
use AlphaLemon\ThemeEngineBundle\Controller\ThemesController as BaseController;
use AlphaLemon\PageTreeBundle\Core\Tools\AlToolkit;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Finder\Finder;
use AlphaLemon\AlValumUploaderBundle\Core\Options\AlValumUploaderOptionsBuilder;

class ThemesController extends BaseController
{
    public function activateCmsThemeAction($themeName, $languageName, $pageName)
    {
        try {
            $this->getActiveTheme()->writeActiveTheme($themeName);
            $url = $this->container->get('router')->generate('_navigation', array('_locale' => $languageName, 'page' => $pageName));

            return new RedirectResponse($url);
        } catch (Exception $e) {
            throw new NotFoundHttpException($e->getMessage());
        }
    }

    public function showThemeFixerAction()
    {
        return $this->renderThemeFixer();
    }

    public function fixThemeAction()
    {
        try {
            $error = null;
            $request = $this->container->get('request');
            
            $params = array();
            $data = explode('&', $request->get('data'));
            foreach ($data as $value) {
                $tmp = preg_split('/=/', $value);
                if ($tmp[0] == 'al_page_to_fix') {
                    $params[$tmp[0]][] = $tmp[1];
                } else {
                    $params[$tmp[0]] = $tmp[1];
                }
            }

            if (empty($params['al_page_to_fix'])) {
                $error = 'Any page has been selected';
                
                return $this->renderThemeFixer($error);
            }

            $pageManager = $this->container->get('alpha_lemon_cms.page_manager');
            $factoryRepository = $this->container->get('alpha_lemon_cms.factory_repository');
            $pagesRepository = $factoryRepository->createRepository('Page');
            foreach ($params['al_page_to_fix'] as $pageId) {
                $alPage = $pagesRepository->fromPK($pageId);
                $pageManager->set($alPage);
                if (false === $pageManager->save(array('TemplateName' => $params['al_template']))) {
                    $error = sprintf('An error occoured when saving the new template for the page %s. Operation aborted', $alPage->getPageName());
                
                    return $this->renderThemeFixer($error);
                }
            }

            return $this->renderThemeFixer($error);
        } catch (\Exception $e) {
            $error = 'An error occourced: ' . $e->getMessage();
                
            return $this->renderThemeFixer($error);
        }
    }

    protected function renderThemeFixer($error = null)
    {
        $request = $this->container->get('request');
        $themeName = $request->get('themeName');

        $themes = $this->container->get('alpha_lemon_theme_engine.themes');
        $theme = $themes->getTheme($themeName);
        $templates = array_keys($theme->getTemplates());

        $factoryRepository = $this->container->get('alpha_lemon_cms.factory_repository');
        $pagesRepository = $factoryRepository->createRepository('Page');
        $pages = $pagesRepository->activePages();

        return $this->container->get('templating')->renderResponse('AlphaLemonCmsBundle:Themes:show_theme_fixer.html.twig', array('templates' => $templates, 'pages' => $pages, 'themeName' => $themeName, 'error' => $error));
    }
}
